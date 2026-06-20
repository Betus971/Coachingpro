<?php

declare(strict_types=1);

namespace App\Enum;

/** Niveau d'activité → multiplicateur du BMR pour obtenir le TDEE (maintien). */
enum ActivityLevel: string
{
    case Sedentary  = 'sedentary';   // bureau, peu de marche
    case Light      = 'light';       // 1-2 séances/sem
    case Moderate   = 'moderate';    // 3 séances/sem
    case Active     = 'active';      // 4-5 séances/sem
    case VeryActive = 'very_active'; // 6+ séances ou gros cardio

    public function multiplier(): float
    {
        return match ($this) {
            self::Sedentary  => 1.2,
            self::Light      => 1.375,
            self::Moderate   => 1.55,
            self::Active     => 1.725,
            self::VeryActive => 1.9,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sedentary  => 'Sédentaire',
            self::Light      => 'Léger',
            self::Moderate   => 'Modéré',
            self::Active     => 'Actif',
            self::VeryActive => 'Très actif',
        };
    }
}
