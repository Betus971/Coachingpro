<?php

declare(strict_types=1);

namespace App\Service\Nutrition;

use App\Entity\User;
use App\Enum\ActivityLevel;
use App\Repository\GoalRepository;
use App\Repository\WeightLogRepository;
use App\Repository\WorkoutSessionRepository;

/**
 * Calcul nutritionnel DÉTERMINISTE (le LLM n'invente aucun chiffre) :
 *  - BMR via Mifflin-St Jeor,
 *  - TDEE = BMR × multiplicateur d'activité (auto-estimé ou forcé),
 *  - macros conseillées pour une sèche musculaire (déficit + priorité protéines).
 *
 * Retourne null si le profil est incomplet (taille, sexe, date de naissance,
 * ou aucune pesée).
 */
final class NutritionCalculator
{
    private const CUT_DEFICIT = 500;
    private const PROTEIN_PER_KG = 2.0;
    private const FAT_KCAL_RATIO = 0.25;

    public function __construct(
        private readonly WeightLogRepository      $weightRepo,
        private readonly WorkoutSessionRepository $sessionRepo,
        private readonly GoalRepository           $goalRepo,
    ) {}

    public function compute(User $user, ?ActivityLevel $override = null): ?NutritionPlan
    {
        $height = $user->getHeightCm();
        $age    = $user->getAge();
        $sex    = $user->getSex();

        $lastWeight = $this->weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'DESC']);
        $weight     = $lastWeight ? (float) $lastWeight->getWeightKg() : null;

        if ($height === null || $age === null || $sex === null || $weight === null) {
            return null;
        }

        $isMale = in_array(strtolower($sex), ['m', 'male', 'homme', 'h'], true);
        $bmr = 10 * $weight + 6.25 * $height - 5 * $age + ($isMale ? 5 : -161);

        $auto     = $override === null;
        $activity = $override ?? $this->estimateActivity($user);
        $tdee     = $bmr * $activity->multiplier();

        $targetKcal = (int) round(max($tdee - self::CUT_DEFICIT, $bmr * 1.2));

        // Protéines sur le poids CIBLE, pas le poids actuel : sur une personne en surpoids,
        // doser sur le poids total surestime (la masse grasse n'a pas besoin de protéines).
        // Cible = objectif si défini, sinon poids pour un IMC de 25 (borné au poids actuel).
        $goal = $this->goalRepo->findOpenForUser($user)[0] ?? null;
        if ($goal !== null) {
            $refWeight = (float) $goal->getTargetValue();
        } else {
            $idealWeight = 25.0 * (($height / 100) ** 2);
            $refWeight   = min($weight, $idealWeight);
        }
        $proteinsG = (int) round(self::PROTEIN_PER_KG * $refWeight);
        $fatsG     = (int) round(($targetKcal * self::FAT_KCAL_RATIO) / 9);
        $carbsG    = (int) max(0, round(($targetKcal - $proteinsG * 4 - $fatsG * 9) / 4));

        return new NutritionPlan(
            bmr:          (int) round($bmr),
            tdee:         (int) round($tdee),
            activity:     $activity,
            activityAuto: $auto,
            targetKcal:   $targetKcal,
            proteinsG:    $proteinsG,
            carbsG:       $carbsG,
            fatsG:        $fatsG,
            weightKg:     $weight,
            deficitKcal:  (int) round($tdee) - $targetKcal,
        );
    }

    private function estimateActivity(User $user): ActivityLevel
    {
        $recent = $this->sessionRepo->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.user = :user')
            ->andWhere('s.performedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', new \DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $n = (int) $recent;

        return match (true) {
            $n >= 6 => ActivityLevel::VeryActive,
            $n >= 4 => ActivityLevel::Active,
            $n >= 2 => ActivityLevel::Moderate,
            $n >= 1 => ActivityLevel::Light,
            default => ActivityLevel::Sedentary,
        };
    }
}
