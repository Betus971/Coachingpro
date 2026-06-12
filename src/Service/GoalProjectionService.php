<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goal;
use App\Entity\GoalAdjustment;
use App\Enum\AdjustmentTrigger;
use App\Enum\GoalMode;
use App\Enum\GoalStatus;
use App\Repository\WeightLogRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coeur DÉTERMINISTE de l'ajustement d'objectif. Aucune IA ici : régression
 * linéaire sur l'historique réel (WeightLog) pour projeter la trajectoire,
 * puis décision de modulation selon Goal.mode.
 *
 * Le LLM (MistralCoachService) intervient APRÈS, uniquement pour expliquer
 * et proposer des tweaks de programme — jamais pour calculer une date.
 *
 * Hypothèse MVP : objectif basé sur le poids (weight_kg). À généraliser via
 * une stratégie par GoalType::metric() pour les perfs (e1rm) plus tard.
 */
final readonly class GoalProjectionService
{
    /** Tolérance d'écart avant de déclencher une modulation (±%). */
    private const DEVIATION_THRESHOLD = 10.0;

    /** Rythme de perte de poids jugé sain, garde-fou (kg/sem). */
    private const SAFE_MAX_RATE = 1.0;
    private const SAFE_MIN_RATE = 0.25;

    public function __construct(
        private WeightLogRepository    $weightRepo,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Analyse un objectif et persiste un GoalAdjustment si une modulation
     * est nécessaire. Retourne la projection (front : courbe prévisionnelle)
     * ET l'ajustement créé le cas échéant (handler : enrichissement IA).
     */
    public function evaluate(Goal $goal): GoalEvaluation
    {
        $proj = $this->project($goal);

        if (!$goal->getStatus()->isOpen() || !$proj->hasEnoughData) {
            return new GoalEvaluation($proj, null);
        }

        // Objectif déjà atteint -> clôture système.
        if ($proj->isAchieved) {
            $adj = $this->record($goal, AdjustmentTrigger::System, 'status', null, GoalStatus::Achieved->value, $proj,
                'Objectif atteint. Bravo.');
            $goal->setStatus(GoalStatus::Achieved);
            $goal->touch();
            $this->em->flush();
            return new GoalEvaluation($proj, $adj);
        }

        if (abs($proj->deviationPercent) < self::DEVIATION_THRESHOLD) {
            return new GoalEvaluation($proj, null); // dans les clous, rien à moduler.
        }

        // Hors tolérance -> on module la dimension autorisée par le mode.
        $adj = match ($goal->getMode()) {
            GoalMode::FixedDeadline => $this->adjustRate($goal, $proj),
            GoalMode::FixedRate     => $this->adjustDeadline($goal, $proj),
            GoalMode::FixedTarget   => $this->adjustTarget($goal, $proj),
        };

        $goal->setStatus(GoalStatus::Recalibrated);
        $goal->touch();
        $this->em->flush();

        return new GoalEvaluation($proj, $adj);
    }

    /**
     * Régression linéaire (moindres carrés) sur les pesées : value = a*jours + b.
     * La pente `a` donne le rythme réel observé.
     */
    public function project(Goal $goal): Projection
    {
        $logs = $this->weightRepo->findBy(['user' => $goal->getUser()], ['loggedOn' => 'ASC']);

        $points = [];
        $t0 = $goal->getStartDate()->getTimestamp();
        foreach ($logs as $log) {
            if ($log->getLoggedOn() < $goal->getStartDate()) {
                continue;
            }
            $days = ($log->getLoggedOn()->getTimestamp() - $t0) / 86400;
            $points[] = [$days, (float) $log->getWeightKg()];
        }

        if (count($points) < 3) {
            return Projection::insufficient();
        }

        [$slopePerDay, $intercept] = $this->linearRegression($points);
        $observedWeeklyRate = abs($slopePerDay) * 7;

        $current      = end($points)[1];
        $target       = (float) $goal->getTargetValue();
        $remaining    = abs($current - $target);
        $isAscending  = $goal->getType()->isAscending();
        $progressing  = $isAscending ? $slopePerDay > 0 : $slopePerDay < 0;

        $isAchieved = $isAscending ? $current >= $target : $current <= $target;

        // ETA en jours pour atteindre la cible au rythme observé.
        $etaDays = ($observedWeeklyRate > 0 && $progressing)
            ? ($remaining / ($observedWeeklyRate / 7))
            : INF;

        $etaDate = is_finite($etaDays)
            ? $goal->getStartDate()->modify('+' . (int) ceil((end($points)[0]) + $etaDays) . ' days')
            : null;

        // Écart vs trajectoire idéale : où devrait-on être aujourd'hui vs où on est.
        $deviation = $this->deviation($goal, $current, end($points)[0]);

        return new Projection(
            hasEnoughData:      true,
            observedWeeklyRate: round($observedWeeklyRate, 3),
            currentValue:       round($current, 2),
            etaDate:            $etaDate,
            deviationPercent:   round($deviation, 2),
            isAchieved:         $isAchieved,
            isProgressing:      $progressing,
        );
    }

    // ─── Stratégies de modulation ───────────────────────────────────────────

    /** Délai fixe : on recalcule le rythme requis et on alerte si > zone sûre. */
    private function adjustRate(Goal $goal, Projection $proj): GoalAdjustment
    {
        $weeksLeft = max(0.5, ($goal->getTargetDate()->getTimestamp() - time()) / (86400 * 7));
        $remaining = abs($proj->currentValue - (float) $goal->getTargetValue());
        $requiredRate = round($remaining / $weeksLeft, 2);

        $clamped = min(self::SAFE_MAX_RATE, max(self::SAFE_MIN_RATE, $requiredRate));
        $reason = $requiredRate > self::SAFE_MAX_RATE
            ? sprintf('Pour tenir la deadline il faudrait %.2f kg/sem, au-delà de la zone saine (%.2f). Rythme plafonné, la deadline risque de glisser.', $requiredRate, self::SAFE_MAX_RATE)
            : sprintf('Rythme requis recalculé à %.2f kg/sem pour tenir l\'échéance.', $requiredRate);

        $adj = $this->record($goal, AdjustmentTrigger::Ai, 'rate', $goal->getWeeklyRate(), (string) $clamped, $proj, $reason);
        $goal->setWeeklyRate((string) $clamped);

        return $adj;
    }

    /** Rythme fixe : on repousse / avance la deadline selon l'ETA projetée. */
    private function adjustDeadline(Goal $goal, Projection $proj): GoalAdjustment
    {
        if ($proj->etaDate === null) {
            return $this->record($goal, AdjustmentTrigger::Ai, 'deadline', $goal->getTargetDate()->format('Y-m-d'), null, $proj,
                'Aucune progression mesurable : impossible de projeter une échéance. Revoir le programme.');
        }

        $old = $goal->getTargetDate();
        $adj = $this->record($goal, AdjustmentTrigger::Ai, 'deadline',
            $old->format('Y-m-d'), $proj->etaDate->format('Y-m-d'), $proj,
            sprintf('Au rythme actuel (%.2f kg/sem), échéance réaliste repoussée de %s à %s.',
                $proj->observedWeeklyRate, $old->format('d/m/Y'), $proj->etaDate->format('d/m/Y')));
        $goal->setTargetDate($proj->etaDate);

        return $adj;
    }

    /** Cible fixe (recomp) : on ne déplace pas la date, on documente l'écart. */
    private function adjustTarget(Goal $goal, Projection $proj): GoalAdjustment
    {
        return $this->record($goal, AdjustmentTrigger::Ai, 'target',
            $goal->getTargetValue(), $goal->getTargetValue(), $proj,
            'Mode cible figée : écart constaté, ajustement laissé au coach.');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function record(
        Goal $goal,
        AdjustmentTrigger $trigger,
        string $dimension,
        ?string $previous,
        ?string $new,
        Projection $proj,
        string $reason,
    ): GoalAdjustment {
        $adj = (new GoalAdjustment())
            ->setTrigger($trigger)
            ->setDimension($dimension)
            ->setPreviousValue($previous)
            ->setNewValue($new)
            ->setProjectedValue($proj->etaDate ? null : (string) $proj->currentValue)
            ->setDeviationPercent((string) $proj->deviationPercent)
            ->setReason($reason);
        $goal->addAdjustment($adj);
        $this->em->persist($adj);

        return $adj;
    }

    /**
     * Écart % entre la valeur attendue (trajectoire linéaire idéale start->target)
     * à l'instant t et la valeur réelle. Négatif = en retard.
     */
    private function deviation(Goal $goal, float $current, float $daysElapsed): float
    {
        $totalDays = max(1, ($goal->getTargetDate()->getTimestamp() - $goal->getStartDate()->getTimestamp()) / 86400);
        $fraction  = min(1.0, $daysElapsed / $totalDays);

        $start    = (float) $goal->getStartValue();
        $target   = (float) $goal->getTargetValue();
        $expected = $start + ($target - $start) * $fraction;

        $plannedDelta = $expected - $start;   // ce qu'on aurait dû parcourir
        $actualDelta  = $current - $start;     // ce qu'on a parcouru
        if (abs($plannedDelta) < 0.01) {
            return 0.0;
        }
        return ($actualDelta / $plannedDelta - 1.0) * 100.0;
    }

    /**
     * Moindres carrés ordinaires. @param list<array{0: float, 1: float}> $points
     * @return array{0: float, 1: float} [pente/jour, ordonnée]
     */
    private function linearRegression(array $points): array
    {
        $n = count($points);
        $sx = $sy = $sxy = $sxx = 0.0;
        foreach ($points as [$x, $y]) {
            $sx += $x; $sy += $y; $sxy += $x * $y; $sxx += $x * $x;
        }
        $denom = ($n * $sxx) - ($sx * $sx);
        if (abs($denom) < 1e-9) {
            return [0.0, $sy / $n];
        }
        $slope = (($n * $sxy) - ($sx * $sy)) / $denom;
        $intercept = ($sy - $slope * $sx) / $n;
        return [$slope, $intercept];
    }
}

/** DTO immuable de projection — consommé par l'IA et le front. */
final readonly class Projection
{
    public function __construct(
        public bool                $hasEnoughData,
        public float               $observedWeeklyRate = 0.0,
        public float               $currentValue = 0.0,
        public ?\DateTimeImmutable $etaDate = null,
        public float               $deviationPercent = 0.0,
        public boo