<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * LE champ qui rend l'objectif "modulable". Il fixe la variable bloquée
 * et désigne donc celle que l'IA a le droit d'ajuster.
 *
 * Un objectif lie 3 variables : cible (targetValue), délai (targetDate),
 * rythme requis (≈ |cible - départ| / durée). On ne peut pas les flexer
 * toutes en même temps — ce mode tranche.
 */
enum GoalMode: string
{
    /** Le délai est sacré (compét, événement). L'IA ajuste le rythme + le programme. */
    case FixedDeadline = 'fixed_deadline';

    /** On garde un rythme sain. L'IA repousse / avance la deadline. */
    case FixedRate = 'fixed_rate';

    /** La cible bouge (recomposition). Rare. */
    case FixedTarget = 'fixed_target';

    public function label(): string
    {
        return match ($this) {
            self::FixedDeadline => 'Échéance fixe (rythme ajustable)',
            self::FixedRate     => 'Rythme fixe (échéance ajustable)',
            self::FixedTarget   => 'Cible fixe (recomposition)',
        };
    }

    /** Variable que le moteur d'ajustement a le droit de modifier. */
    public function adjustableDimension(): string
    {
        return match ($this) {
            self::FixedDeadline => 'rate',
            self::FixedRate     => 'deadline',
            self::FixedTarget   => 'target',
        };
    }
}
