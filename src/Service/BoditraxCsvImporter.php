<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\WeightLog;
use App\Repository\WeightLogRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Parse le format CSV RÉEL de Boditrax :
 *
 *   BodyMetricTypeId,Value,CreatedDate
 *   MuscleMass,79,5,03/05/2026 06:02:00   ← valeur décimale française "79,5" = 4 champs !
 *   LegMuscleScore,82,03/05/2026 06:02:00 ← valeur entière = 3 champs
 *
 * Format long : une ligne par métrique par session.
 * On groupe par date (à la seconde) pour reconstituer chaque séance Boditrax.
 *
 * Mapping des métriques → champs WeightLog :
 *   BodyWeight           → weightKg
 *   MuscleMass           → muscleKg
 *   FatMass              → fatKg  (+calcul fatPercent)
 *   BoneMass             → boneKg
 *   WaterMass            → waterKg → waterPercent calculé
 *   BodyMassIndex        → bmi
 *   BasalMetabolicRatekJ → bmr (converti kJ→kcal : ÷ 4.184)
 */
class BoditraxCsvImporter
{
    /** Métriques qu'on importe (les autres sont ignorées) */
    private const METRIC_MAP = [
        'BodyWeight'            => 'weight',
        'MuscleMass'            => 'muscle',
        'FatMass'               => 'fat',
        'BoneMass'              => 'bone',
        'WaterMass'             => 'water',
        'BodyMassIndex'         => 'bmi',
        'BasalMetabolicRatekJ'  => 'bmr_kj',
        'FatFreeMass'           => 'fat_free',
        'VisceralFatRating'     => 'visceral_fat',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WeightLogRepository $weightRepo,
    ) {
    }

    /**
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function import(string $csvContent, User $user): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        // Supprimer BOM UTF-8 (toutes variantes)
        $csvContent = str_replace("\xEF\xBB\xBF", '', $csvContent);
        // Normaliser les retours chariot Windows/Mac
        $csvContent = str_replace("\r\n", "\n", $csvContent);
        $csvContent = str_replace("\r", "\n", $csvContent);

        // Réindexer le tableau pour que array_shift fonctionne
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $csvContent)),
            fn($l) => $l !== ''
        ));

        if (count($lines) < 2) {
            $result['errors'][] = 'Fichier vide.';
            return $result;
        }

        // Chercher la ligne header "BodyMetricTypeId" (peut ne pas être en ligne 1)
        $dataStart = null;
        foreach ($lines as $i => $line) {
            $lineClean = strtolower(str_replace([' ', '\t'], '', $line));
            if (str_contains($lineClean, 'bodymetrictypeid')) {
                $dataStart = $i;
                break;
            }
        }

        if ($dataStart === null) {
            $result['errors'][] = 'En-tête "BodyMetricTypeId" introuvable dans le fichier. Premières lignes reçues: "'.implode(' | ', array_slice($lines, 0, 3)).'"';
            return $result;
        }

        // Ignorer toutes les lignes avant le header + le header lui-même
        $lines = array_slice($lines, $dataStart + 1);
        $sessions = []; // ['2026-05-03' => ['weight' => 121.2, 'muscle' => 79.5, ...]]

        foreach ($lines as $lineNum => $line) {
            [$metric, $value, $dateStr] = $this->parseLine($line);
            if ($metric === null) continue;

            $key = self::METRIC_MAP[$metric] ?? null;
            if ($key === null) continue; // métrique non mappée → ignorer

            try {
                $date = \DateTimeImmutable::createFromFormat('d/m/Y H:i:s', $dateStr)
                    ?: \DateTimeImmutable::createFromFormat('d/m/Y H:i', $dateStr);

                if (!$date) {
                    $result['errors'][] = "Ligne ".($lineNum + 2)." : date invalide ($dateStr).";
                    continue;
                }

                $dayKey = $date->format('Y-m-d');
                $sessions[$dayKey][$key] = $value;
                $sessions[$dayKey]['_date'] = $date->setTime(0, 0);

            } catch (\Throwable $e) {
                $result['errors'][] = "Ligne ".($lineNum + 2)." : ".$e->getMessage();
            }
        }

        // Persister chaque session
        foreach ($sessions as $dayKey => $data) {
            if (!isset($data['weight'])) {
                $result['errors'][] = "Session $dayKey : poids (BodyWeight) manquant → ignorée.";
                $result['skipped']++;
                continue;
            }

            $weight = (float) $data['weight'];
            if ($weight < 30 || $weight > 350) {
                $result['errors'][] = "Session $dayKey : poids invalide ($weight kg) → ignorée.";
                $result['skipped']++;
                continue;
            }

            /** @var \DateTimeImmutable $date */
            $date = $data['_date'];

            $log = $this->weightRepo->findOneBy(['user' => $user, 'loggedOn' => $date])
                ?? new WeightLog();

            $log->setUser($user);
            $log->setLoggedOn($date);
            $log->setWeightKg(number_format($weight, 2, '.', ''));
            $log->setSource('boditrax_csv');

            if (isset($data['muscle'])) {
                $log->setMuscleKg(number_format((float)$data['muscle'], 2, '.', ''));
            }
            if (isset($data['fat'])) {
                $fatKg = (float)$data['fat'];
                $log->setFatKg(number_format($fatKg, 2, '.', ''));
                // Calcul % graisse = (fat_kg / weight_kg) * 100
                $log->setFatPercent(number_format(($fatKg / $weight) * 100, 1, '.', ''));
            }
            if (isset($data['bone'])) {
                $log->setBoneKg(number_format((float)$data['bone'], 2, '.', ''));
            }
            if (isset($data['water'])) {
                $waterKg = (float)$data['water'];
                // Calcul % eau = (water_kg / weight_kg) * 100
                $log->setWaterPercent(number_format(($waterKg / $weight) * 100, 1, '.', ''));
            }
            if (isset($data['bmi'])) {
                $log->setBmi(number_format((float)$data['bmi'], 1, '.', ''));
            }
            if (isset($data['bmr_kj'])) {
                // Convertir kJ → kcal (1 kcal = 4.184 kJ)
                $log->setBmr((int) round((float)$data['bmr_kj'] / 4.184));
            }

            // Notes enrichies avec les extras
            $extras = [];
            if (isset($data['visceral_fat'])) $extras[] = "Graisse viscérale: ".$data['visceral_fat'];
            if ($extras) $log->setNotes(implode(' · ', $extras));

            $this->em->persist($log);
            $result['imported']++;
        }

        $this->em->flush();
        return $result;
    }

    /**
     * Parse une ligne avec la virgule décimale française.
     *
     * Cas 1 — valeur entière :   "LegMuscleScore,82,03/05/2026 06:02:00"
     *   → split = ['LegMuscleScore', '82', '03/05/2026 06:02:00']  (3 éléments)
     *
     * Cas 2 — valeur décimale :  "MuscleMass,79,5,03/05/2026 06:02:00"
     *   → split = ['MuscleMass', '79', '5', '03/05/2026 06:02:00']  (4 éléments)
     *   → valeur = "79.5"
     *
     * Heuristique : le dernier champ contient toujours "/" (date), donc on peut
     * détecter si le 3e champ est la date ou la partie décimale.
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function parseLine(string $line): array
    {
        $parts = explode(',', $line);
        $count = count($parts);

        if ($count < 3) return [null, null, null];

        $metric = trim($parts[0]);

        // Le champ date contient toujours "/" et ":"
        // On détermine s'il y a une partie décimale en regardant si parts[2] ressemble à une date
        if ($count === 3) {
            // Cas entier : metric, value, date
            return [$metric, trim($parts[1]), trim($parts[2])];
        }

        if ($count === 4) {
            // Cas décimal : metric, int_part, dec_part, date
            $intPart = trim($parts[1]);
            $decPart = trim($parts[2]);
            $dateStr = trim($parts[3]);

            // Vérifier que decPart est bien un nombre (pas une date)
            if (is_numeric($decPart) && str_contains($dateStr, '/')) {
                $value = $intPart . '.' . $decPart;
                return [$metric, $value, $dateStr];
            }

            // Fallback : peut-être 3 champs avec date complexe
            return [$metric, $intPart, implode(',', array_slice($parts, 2))];
        }

        // Cas inattendu (5+ champs) → essayer de reconstituer
        $metric  = trim($parts[0]);
        $dateStr = trim($parts[$count - 1]);
        if (str_contains($dateStr, '/') && str_contains($dateStr, ':')) {
            $value = implode('.', array_slice($parts, 1, $count - 2));
            return [$metric, $value, $dateStr];
        }

        return [null, null, null];
    }
}
