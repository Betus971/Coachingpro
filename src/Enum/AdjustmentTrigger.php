<?php

declare(strict_types=1);

namespace App\Enum;

/** Qui / quoi a déclenché une modulation de l'objectif. Sert à l'audit SaaS. */
enum AdjustmentTrigger: string
{
    case Ai     = 'ai';      // moteur de projection automatique
    case Coach  = 'coach';   // override manuel du coach
    case User   = 'user';    // l'utilisateur a changé sa cible / deadline
    case System = 'system';  // ex. objectif atteint -> clôture auto
}
