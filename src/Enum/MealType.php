<?php

declare(strict_types=1);

namespace App\Enum;

enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch     = 'lunch';
    case Dinner    = 'dinner';
    case Snack     = 'snack';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'Petit-déj',
            self::Lunch     => 'Déjeuner',
            self::Dinner    => 'Dîner',
            self::Snack     => 'Collation',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Breakfast => 'bi-cup-hot',
            self::Lunch     => 'bi-egg-fried',
            self::Dinner    => 'bi-moon-stars',
            self::Snack     => 'bi-cookie',
        };
    }
}
