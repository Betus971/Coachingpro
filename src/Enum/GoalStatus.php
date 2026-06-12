<?php

declare(strict_types=1);

namespace App\Enum;

enum GoalStatus: string
{
    case Active      = 'active';
    case Achieved    = 'achieved';
    case Paused      = 'paused';
    case Recalibrated = 'recalibrated'; // ajusté au moins une fois, toujours en cours
    case Abandoned   = 'abandoned';

    public function isOpen(): bool
    {
        return $this === self::Active || $this === self::Recalibrated;
    }
}
