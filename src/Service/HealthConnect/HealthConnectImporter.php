<?php

declare(strict_types=1);

namespace App\Service\HealthConnect;

use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Repository\WorkoutSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importe les séances d'un export Health Connect (.db SQLite) vers WorkoutSession.
 *
 * Ce que Health Connect fournit : un bloc temps + type + métriques agrégées
 * (durée, calories, FC moyenne). PAS de détail séries/reps/charges — donc on
 * crée des séances "cardio-like", à compléter manuellement pour la muscu.
 *
 * Idempotent : l'UUID Health Connect de chaque séance est stocké dans les notes
 * (marqueur "hc:<uuid>"). Un ré-import ignore ce qui est déjà présent.
 *
 * On ne touche NI au poids NI à la nutrition (vides côté Samsung Health).
 */
final class HealthConnectImporter
{
    private const SOURCE_TAG = 'Importé depuis Health Connect (Samsung Health).';

    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly WorkoutSessionRepository $sessionRepo,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function import(string $dbPath, User $user, bool $dryRun = false): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        if (!is_readable($dbPath)) {
            $result['errors'][] = "Fichier illisible : $dbPath";
            return $result;
        }

        try {
            $pdo = new \PDO('sqlite:' . $dbPath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            $result['errors'][] = 'Ouverture SQLite impossible : ' . $e->getMessage();
            return $result;
        }

        // Idempotence : on récupère les uuid Health Connect déjà importés pour ce user.
        $already = $this->existingHcUuids($user);

        $sql = <<<SQL
            SELECT hex(e.uuid)      AS hc_uuid,
                   e.start_time      AS start_ms,
                   e.end_time        AS end_ms,
                   e.exercise_type   AS type,
                   c.energy          AS energy,
                   (SELECT AVG(s.beats_per_minute)
                      FROM heart_rate_record_series_table s
                     WHERE s.epoch_millis BETWEEN e.start_time AND e.end_time) AS avg_hr
              FROM exercise_session_record_table e
              LEFT JOIN total_calories_burned_record_table c ON c.start_time = e.start_time
             ORDER BY e.start_time ASC
        SQL;

        try {
            $rows = $pdo->query($sql);
        } catch (\PDOException $e) {
            $result['errors'][] = 'Requête séances échouée : ' . $e->getMessage();
            return $result;
        }

        $batch = 0;
        foreach ($rows as $row) {
            $uuid = (string) $row['hc_uuid'];
            if ($uuid !== '' && isset($already[$uuid])) {
                $result['skipped']++;
                continue;
            }

            try {
                $session = $this->toSession($row, $user);
            } catch (\Throwable $e) {
                $result['errors'][] = sprintf('Séance %s ignorée : %s', $uuid, $e->getMessage());
                continue;
            }

            if (!$dryRun) {
                $this->em->persist($session);
                if (++$batch % 50 === 0) {
                    $this->em->flush();
                }
            }
            $already[$uuid] = true;
            $result['imported']++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $result;
    }

    private function toSession(array $row, User $user): WorkoutSession
    {
        $startMs = (int) $row['start_ms'];
        $endMs   = (int) $row['end_ms'];
        $type    = (int) $row['type'];

        $performedAt = (new \DateTimeImmutable())->setTimestamp(intdiv($startMs, 1000));

        $durationMin = (int) round(max(0, $endMs - $startMs) / 60000);
        $durationMin = max(0, min(600, $durationMin)); // borne entité (0..600)

        $kcal = $row['energy'] !== null ? (int) round(((float) $row['energy']) / 1000) : null;
        $avgHr = $row['avg_hr'] !== null ? (int) round((float) $row['avg_hr']) : null;

        $notesParts = [self::SOURCE_TAG, 'hc:' . $row['hc_uuid'], 'type HC ' . $type];
        if ($kcal !== null)  { $notesParts[] = $kcal . ' kcal'; }
        if ($avgHr !== null) { $notesParts[] = 'FC moy ' . $avgHr . ' bpm'; }

        $session = (new WorkoutSession())
            ->setUser($user)
            ->setName(ExerciseTypeMap::label($type))
            ->setPerformedAt($performedAt)
            ->setNotes(implode(' · ', $notesParts));

        if ($durationMin > 0) {
            $session->setDurationMinutes($durationMin);
        }

        return $session;
    }

    /**
     * UUID Health Connect déjà importés pour ce user (parsés depuis le marqueur "hc:<uuid>").
     *
     * @return array<string, true>
     */
    private function existingHcUuids(User $user): array
    {
        $set = [];
        foreach ($this->sessionRepo->findBy(['user' => $user]) as $s) {
            if ($s->getNotes() && preg_match('/hc:([0-9a-fA-F-]{8,})/', $s->getNotes(), $m)) {
                $set[$m[1]] = true;
            }
        }
        return $set;
    }
}
