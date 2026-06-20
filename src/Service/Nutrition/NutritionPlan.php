<?php

declare(strict_types=1);

namespace App\Service\Nutrition;

use App\Enum\ActivityLevel;

/** Résultat d'un calcul nutritionnel (BMR / TDEE / macros conseillées). */
final readonly class NutritionPlan
{
    public function __construct(
        public int           $bmr,
        public int           $tdee,
        public ActivityLevel $activity,
        public bool          $activityAuto,
        public int           $targetKcal,
        public int           $proteinsG,
        public int           $carbsG,
        public int           $fatsG,
        public float         $weightKg,
        public int           $deficitKcal,
    ) {}
}
