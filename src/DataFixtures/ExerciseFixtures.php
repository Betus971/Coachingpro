<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exercise;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Catalogue d'exercices — données de référence partagées.
 * Chargé en premier (pas de dépendance vers User).
 */
class ExerciseFixtures extends Fixture
{
    // Constantes pour référencer les exercices depuis les autres fixtures
    public const BENCH_PRESS        = 'exercise_bench_press';
    public const INCLINE_DB_PRESS   = 'exercise_incline_db_press';
    public const LATERAL_RAISE      = 'exercise_lateral_raise';
    public const MILITARY_PRESS     = 'exercise_military_press';
    public const DIPS               = 'exercise_dips';
    public const CARDIO_LISS        = 'exercise_cardio_liss';
    public const RDL                = 'exercise_rdl';
    public const LAT_PULLDOWN       = 'exercise_lat_pulldown';
    public const BARBELL_ROW        = 'exercise_barbell_row';
    public const CABLE_ROW          = 'exercise_cable_row';
    public const EZ_CURL            = 'exercise_ez_curl';
    public const SQUAT              = 'exercise_squat';
    public const LEG_PRESS          = 'exercise_leg_press';
    public const LUNGES             = 'exercise_lunges';
    public const LEG_CURL           = 'exercise_leg_curl';
    public const HIP_THRUST         = 'exercise_hip_thrust';
    public const CALF_RAISE         = 'exercise_calf_raise';
    public const CARDIO_LONG        = 'exercise_cardio_long';

    /** @var array<string, array{name: string, muscle: string, equipment: string|null, ref: string}> */
    private const CATALOGUE = [
        // ── PUSH ──────────────────────────────────────────────────────────────
        self::BENCH_PRESS      => ['name' => 'Développé couché barre',            'muscle' => 'chest',     'equipment' => 'barbell'],
        self::INCLINE_DB_PRESS => ['name' => 'Développé incliné haltères',         'muscle' => 'chest',     'equipment' => 'dumbbell'],
        self::LATERAL_RAISE    => ['name' => 'Élévations latérales',               'muscle' => 'shoulders', 'equipment' => 'dumbbell'],
        self::MILITARY_PRESS   => ['name' => 'Développé militaire haltères',       'muscle' => 'shoulders', 'equipment' => 'dumbbell'],
        self::DIPS             => ['name' => 'Dips / Pushdown poulie triceps',      'muscle' => 'triceps',   'equipment' => 'bodyweight'],
        // ── PULL ──────────────────────────────────────────────────────────────
        self::RDL              => ['name' => 'Soulevé de terre roumain',            'muscle' => 'back',      'equipment' => 'barbell'],
        self::LAT_PULLDOWN     => ['name' => 'Tirage vertical (tractions assistées)', 'muscle' => 'back',   'equipment' => 'cable'],
        self::BARBELL_ROW      => ['name' => 'Rowing barre ou haltères',            'muscle' => 'back',      'equipment' => 'barbell'],
        self::CABLE_ROW        => ['name' => 'Tirage horizontal poulie',            'muscle' => 'back',      'equipment' => 'cable'],
        self::EZ_CURL          => ['name' => 'Curl barre EZ',                       'muscle' => 'biceps',    'equipment' => 'barbell'],
        // ── LEGS ──────────────────────────────────────────────────────────────
        self::SQUAT            => ['name' => 'Squat barre (ou Hack Squat)',          'muscle' => 'quads',     'equipment' => 'barbell'],
        self::LEG_PRESS        => ['name' => 'Presse à cuisses',                    'muscle' => 'quads',     'equipment' => 'machine'],
        self::LUNGES           => ['name' => 'Fentes marchées haltères',            'muscle' => 'quads',     'equipment' => 'dumbbell'],
        self::LEG_CURL         => ['name' => 'Leg curl allongé',                    'muscle' => 'hamstrings', 'equipment' => 'machine'],
        self::HIP_THRUST       => ['name' => 'Hip Thrust / Ponte fessiers',         'muscle' => 'glutes',    'equipment' => 'barbell'],
        self::CALF_RAISE       => ['name' => 'Mollets debout',                      'muscle' => 'calves',    'equipment' => 'machine'],
        // ── CARDIO ────────────────────────────────────────────────────────────
        self::CARDIO_LISS      => ['name' => 'Cardio LISS — Vélo / Tapis (fin séance)', 'muscle' => 'cardio', 'equipment' => 'machine'],
        self::CARDIO_LONG      => ['name' => 'Cardio longue durée (vélo, natation, elliptique)', 'muscle' => 'cardio', 'equipment' => 'machine'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::CATALOGUE as $ref => $data) {
            $exercise = new Exercise();
            $exercise->setName($data['name']);
            $exercise->setMuscleGroup($data['muscle']);
            $exercise->setEquipment($data['equipment'] ?? null);
            // createdBy = null → exercice système, visible par tout le monde
            $manager->persist($exercise);
            $this->addReference($ref, $exercise);
        }

        $manager->flush();
    }
}
