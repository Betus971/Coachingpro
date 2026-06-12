<?php

declare(strict_types=1);

namespace App\Coach;

use App\Entity\Goal;
use App\Entity\GoalAdjustment;
use App\Entity\ProgramAssignment;
use App\Entity\User;
use App\Enum\AdjustmentTrigger;
use App\Enum\CoachActionType;
use App\Repository\GoalRepository;
use App\Repository\ProgramAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applique de façon DÉTERMINISTE les actions proposées par l'IA coach
 * (durée de programme, objectif, macros). Le LLM ne fait que proposer une
 * action structurée + un résumé ; ici on (re)valide, on scope au user, on
 * exécute et on audite (GoalAdjustment pour tout ce qui touche l'objectif).
 *
 * describe() = résumé lisible pour la confirmation (valide aussi le payload).
 * apply()    = exécution réelle après confirmation utilisateur.
 */
final class CoachActionExecutor
{
    private const MIN_WEEKS = 1;
    private const MAX_WEEKS = 104;

    public function __construct(
        private readonly EntityManagerInterface       $em,
        private readonly GoalRepository               $goalRepo,
        private readonly ProgramAssignmentRepository  $assignmentRepo,
    ) {}

    /**
     * Résumé FR de l'action proposée (lève \InvalidArgumentException si invalide).
     *
     * @param array<string, mixed> $payload
     */
    public function describe(User $user, CoachActionType $type, array $payload): string
    {
        return match ($type) {
            CoachActionType::UpdateProgramDuration => $this->describeDuration($user, $payload),
            CoachActionType::UpdateGoal            => $this->describeGoal($user, $payload),
            CoachActionType::UpdateNutritionTargets => $this->describeNutrition($user, $payload),
            CoachActionType::UpdateWorkoutDay      => throw new \InvalidArgumentException(
                "L'édition du contenu des séances n'est pas encore disponible ici."
            ),
        };
    }

    /**
     * Exécute l'action après confirmation. Retourne un message de confirmation.
     *
     * @param array<string, mixed> $payload
     */
    public function apply(User $user, CoachActionType $type, array $payload): string
    {
        $msg = match ($type) {
            CoachActionType::UpdateProgramDuration  => $this->applyDuration($user, $payload),
            CoachActionType::UpdateGoal             => $this->applyGoal($user, $payload),
            CoachActionType::UpdateNutritionTargets => $this->applyNutrition($user, $payload),
            CoachActionType::UpdateWorkoutDay       => throw new \InvalidArgumentException(
                "L'édition du contenu des séances n'est pas encore disponible ici."
            ),
        };
        $this->em->flush();

        return $msg;
    }

    // ─── Durée du programme ──────────────────────────────────────────────────

    private function describeDuration(User $user, array $payload): string
    {
        $weeks      = $this->intWeeks($payload);
        $assignment = $this->requireActiveAssignment($user);
        $current    = $assignment->getProgram()->getDurationWeeks();
        $newDate    = $assignment->getStartDate()->modify("+{$weeks} weeks");

        return sprintf(
            'Passer la durée du programme « %s » de %s à %d semaines (nouvelle échéance ≈ %s).',
            $assignment->getProgram()->getName(),
            $current !== null ? $current . ' sem' : 'non définie',
            $weeks,
            $newDate->format('d/m/Y'),
        );
    }

    private function applyDuration(User $user, array $payload): string
    {
        $weeks      = $this->intWeeks($payload);
        $assignment = $this->requireActiveAssignment($user);
        $program    = $assignment->getProgram();

        $oldWeeks = $program->getDurationWeeks();
        $program->setDurationWeeks($weeks);

        // Cohérence : on aligne l'échéance de l'objectif ouvert sur la nouvelle durée.
        $goal = $this->activeGoal($user);
        if ($goal !== null) {
            $oldDate = $goal->getTargetDate();
            $newDate = $goal->getStartDate()->modify("+{$weeks} weeks");
            $goal->setTargetDate($newDate);
            $goal->touch();
            $this->audit(
                $goal,
                'deadline',
                $oldDate->format('Y-m-d'),
                $newDate->format('Y-m-d'),
                sprintf('Durée du programme passée de %s à %d semaines (demande utilisateur via le coach IA).',
                    $oldWeeks !== null ? $oldWeeks . ' sem' : 'non définie', $weeks),
            );
        }

        return sprintf('✅ Programme passé à %d semaines%s.', $weeks,
            $goal !== null ? ', échéance recalée' : '');
    }

    // ─── Objectif (cible / échéance / rythme) ────────────────────────────────

    private function describeGoal(User $user, array $payload): string
    {
        $goal  = $this->requireActiveGoal($user);
        $parts = [];
        if (isset($payload['target_value'])) {
            $parts[] = sprintf('cible %.1f → %.1f', (float) $goal->getTargetValue(), (float) $payload['target_value']);
        }
        if (isset($payload['target_date'])) {
            $parts[] = 'échéance → ' . $this->date($payload['target_date'])->format('d/m/Y');
        }
        if (isset($payload['weekly_rate'])) {
            $parts[] = sprintf('rythme → %.2f/sem', (float) $payload['weekly_rate']);
        }
        if ($parts === []) {
            throw new \InvalidArgumentException('Aucun champ d\'objectif à modifier.');
        }

        return 'Mettre à jour ton objectif : ' . implode(', ', $parts) . '.';
    }

    private function applyGoal(User $user, array $payload): string
    {
        $goal    = $this->requireActiveGoal($user);
        $changed = [];

        if (isset($payload['target_value'])) {
            $old = $goal->getTargetValue();
            $new = number_format((float) $payload['target_value'], 2, '.', '');
            $goal->setTargetValue($new);
            $this->audit($goal, 'target', $old, $new, 'Cible modifiée via le coach IA.');
            $changed[] = 'cible';
        }
        if (isset($payload['target_date'])) {
            $old = $goal->getTargetDate();
            $new = $this->date($payload['target_date']);
            $goal->setTargetDate($new);
            $this->audit($goal, 'deadline', $old->format('Y-m-d'), $new->format('Y-m-d'), 'Échéance modifiée via le coach IA.');
            $changed[] = 'échéance';
        }
        if (isset($payload['weekly_rate'])) {
            $goal->setWeeklyRate(number_format((float) $payload['weekly_rate'], 2, '.', ''));
            $changed[] = 'rythme';
        }
        $goal->touch();

        return '✅ Objectif mis à jour (' . implode(', ', $changed) . ').';
    }

    // ─── Macros nutritionnelles ──────────────────────────────────────────────

    private function describeNutrition(User $user, array $payload): string
    {
        $goal = $this->requireActiveGoal($user);
        $map  = $this->nutritionFields($payload);
        if ($map === []) {
            throw new \InvalidArgumentException('Aucune cible nutritionnelle à modifier.');
        }
        $labels = ['kcal' => 'kcal', 'proteins_g' => 'g prot', 'carbs_g' => 'g gluc', 'fats_g' => 'g lip'];
        $parts  = [];
        foreach ($map as $k => $v) {
            $parts[] = $v . ' ' . $labels[$k];
        }

        return 'Fixer tes cibles nutritionnelles : ' . implode(' · ', $parts) . '.';
    }

    private function applyNutrition(User $user, array $payload): string
    {
        $goal = $this->requireActiveGoal($user);
        $map  = $this->nutritionFields($payload);
        if (isset($map['kcal']))       { $goal->setTargetKcal($map['kcal']); }
        if (isset($map['proteins_g'])) { $goal->setTargetProteinsG($map['proteins_g']); }
        if (isset($map['carbs_g']))    { $goal->setTargetCarbsG($map['carbs_g']); }
        if (isset($map['fats_g']))     { $goal->setTargetFatsG($map['fats_g']); }
        $goal->touch();

        return '✅ Cibles nutritionnelles mises à jour.';
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function intWeeks(array $payload): int
    {
        $weeks = (int) ($payload['weeks'] ?? 0);
        if ($weeks < self::MIN_WEEKS || $weeks > self::MAX_WEEKS) {
            throw new \InvalidArgumentException(sprintf('Durée invalide : %d (attendu %d–%d semaines).',
                $weeks, self::MIN_WEEKS, self::MAX_WEEKS));
        }
        return $weeks;
    }

    /** @return array<string, int> */
    private function nutritionFields(array $payload): array
    {
        $out = [];
        foreach (['kcal', 'proteins_g', 'carbs_g', 'fats_g'] as $k) {
            if (isset($payload[$k]) && (int) $payload[$k] > 0) {
                $out[$k] = (int) $payload[$k];
            }
        }
        return $out;
    }

    private function date(mixed $raw): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable((string) $raw);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Date invalide : ' . (string) $raw);
        }
    }

    private function activeGoal(User $user): ?Goal
    {
        return $this->goalRepo->findOpenForUser($user)[0] ?? null;
    }

    private function requireActiveGoal(User $user): Goal
    {
        $goal = $this->activeGoal($user);
        if ($goal === null) {
            throw new \InvalidArgumentException('Aucun objectif actif à modifier.');
        }
        return $goal;
    }

    private function requireActiveAssignment(User $user): ProgramAssignment
    {
        foreach ($this->assignmentRepo->findBy(['user' => $user], ['startDate' => 'DESC']) as $a) {
            if ($a->isActive()) {
                return $a;
            }
        }
        throw new \InvalidArgumentException('Aucun programme actif à modifier.');
    }

    private function audit(Goal $goal, string $dimension, ?string $prev, ?string $new, string $reason): void
    {
        $adj = (new GoalAdjustment())
            ->setTrigger(AdjustmentTrigger::User)
            ->setDimension($dimension)
            ->setPreviousValue($prev)
            ->setNewValue($new)
            ->setReason($reason);
        $goal->addAdjustment($adj);
        $this->em->persist($adj);
    }
}
