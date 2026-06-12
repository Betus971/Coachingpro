<?php

declare(strict_types=1);

namespace App\Service\HealthConnect;

/**
 * Mapping des entiers ExerciseType de Health Connect vers un libellé FR.
 * Source : androidx.health.connect.client.records.ExerciseSessionRecord (constantes officielles).
 *
 * ⚠️ Samsung Health mappe ses propres activités vers cet enum de façon GROSSIÈRE :
 * une séance de muscu peut arriver taguée "Aviron" (53) ou "Gymnastique" (34).
 * On garde donc toujours le code brut dans les notes pour pouvoir re-taguer.
 */
final class ExerciseTypeMap
{
    private const LABELS = [
        0  => 'Autre',
        2  => 'Badminton',
        4  => 'Baseball',
        5  => 'Basketball',
        8  => 'Vélo',
        9  => 'Vélo stationnaire',
        10 => 'Boot camp',
        11 => 'Boxe',
        13 => 'Callisthénie',
        14 => 'Cricket',
        16 => 'Danse',
        25 => 'Elliptique',
        26 => 'Cours collectif',
        27 => 'Escrime',
        28 => 'Football américain',
        29 => 'Football australien',
        31 => 'Frisbee',
        32 => 'Golf',
        33 => 'Respiration guidée',
        34 => 'Gymnastique',
        35 => 'Handball',
        36 => 'HIIT',
        37 => 'Randonnée',
        38 => 'Hockey sur glace',
        39 => 'Patinage sur glace',
        44 => 'Arts martiaux',
        46 => 'Pagaie',
        47 => 'Parapente',
        48 => 'Pilates',
        50 => 'Racquetball',
        51 => 'Escalade',
        52 => 'Roller hockey',
        53 => 'Aviron',
        54 => 'Rameur',
        55 => 'Rugby',
        56 => 'Course à pied',
        57 => 'Course (tapis)',
        58 => 'Voile',
        59 => 'Plongée',
        60 => 'Patinage',
        61 => 'Ski',
        62 => 'Snowboard',
        63 => 'Raquettes',
        64 => 'Football',
        65 => 'Softball',
        66 => 'Squash',
        68 => 'Montée d\'escaliers',
        69 => 'Stepper',
        70 => 'Renforcement musculaire',
        71 => 'Étirements',
        72 => 'Surf',
        73 => 'Natation (eau libre)',
        74 => 'Natation (piscine)',
        75 => 'Tennis de table',
        76 => 'Tennis',
        78 => 'Volleyball',
        79 => 'Marche',
        80 => 'Water-polo',
        81 => 'Haltérophilie',
        82 => 'Fauteuil roulant',
        83 => 'Yoga',
    ];

    public static function label(int $type): string
    {
        return self::LABELS[$type] ?? sprintf('Séance (type %d)', $type);
    }
}
