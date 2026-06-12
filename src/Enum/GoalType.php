<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature de l'objectif. Détermine la métrique suivie et le sens de progression
 * (perte = currentValue doit descendre vers targetValue, gain = monter).
 */
enum GoalType: string
{
    case WeightLoss   = 'weight_loss';   // métrique: weight_kg, sens descendant
    case WeightGain   = 'weight_gain';   // métrique: weight_kg, sens montant
    case BodyFat      = 'body_fat';      // métrique: fat_percent, descendant
    case MuscleGain   = 'muscle_gain';   // métrique: muscle_kg, montant
    case Performance  = 'performance';   // métrique: e1rm sur un exercice, montant

    /** Sens attendu : true = la valeur doit augmenter, false = diminuer. */
    public function isAscending(): bool
    {
        return match ($this) {
            self::WeightGain, self::MuscleGain, self::Performance => true,
            default => false,
        };
    }

    /** Clé de la métrique source (sur WeightLog ou WorkoutSet selon le type). */
    public function metric(): string
    {
        return match ($this) {
            self::WeightLoss, self::WeightGain => 'weight_kg',
            self::BodyFat                       => 'fat_percent',
            self::MuscleGain                    => 'muscle_kg',
            self::Performance                   => 'e1rm',
        };
    }
}
