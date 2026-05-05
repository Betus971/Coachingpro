<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\ExerciseTemplate;
use App\Entity\Program;
use App\Entity\ProgramAssignment;
use App\Entity\User;
use App\Entity\WorkoutTemplate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Programme PPL + Cardio — 121 kg → 95 kg.
 *
 * Structure:
 *   Program "PPL + Cardio — 121→95 kg" (9 mois, ~36 semaines)
 *   ├── Lundi   PUSH  — Poitrine / Épaules / Triceps
 *   ├── Mercredi PULL — Dos / Biceps
 *   ├── Vendredi LEGS — Cuisses / Fessiers / Mollets
 *   └── Samedi  CARDIO DÉDIÉ
 */
class ProgramFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var User $coach */
        $coach = $this->getReference(UserFixtures::COACH_REF, User::class);

        // ── Programme parent ──────────────────────────────────────────────────
        $program = new Program();
        $program->setName('PPL + Cardio — 121 → 95 kg');
        $program->setDescription(
            'Programme 4 jours/semaine. Objectif: perdre ~26 kg de graisse en préservant la masse musculaire. '
            . 'Déficit calorique de ~500 kcal/jour sur maintenance (~3 200 kcal). '
            . '2 700 kcal · 190g Prot · 270g Gluc · 75g Lip.'
        );
        $program->setCreatedBy($coach);
        $program->setDurationWeeks(36); // ~9 mois
        $manager->persist($program);

        // ── Séances ───────────────────────────────────────────────────────────
        $this->createPushSession($manager, $program);
        $this->createPullSession($manager, $program);
        $this->createLegsSession($manager, $program);
        $this->createCardioSession($manager, $program);

        // ── Assignment: programme assigné à soi-même ─────────────────────────
        $assignment = new ProgramAssignment();
        $assignment->setProgram($program);
        $assignment->setUser($coach);
        $assignment->setStartDate(new \DateTimeImmutable('today'));
        $assignment->setIsActive(true);
        $manager->persist($assignment);

        $manager->flush();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PUSH — Lundi (dayOfWeek=1)
    // ──────────────────────────────────────────────────────────────────────────
    private function createPushSession(ObjectManager $manager, Program $program): void
    {
        $session = new WorkoutTemplate();
        $session->setProgram($program);
        $session->setName('PUSH — Poitrine / Épaules / Triceps');
        $session->setDayOfWeek(1); // Lundi
        $manager->persist($session);

        $exercises = [
            // [ref, sets, repsMin, repsMax, weightKg, restSec, notes]
            [ExerciseFixtures::BENCH_PRESS,      4, 6,  8,  null, 180, 'Exercice principal. Priorité à la charge progressive.'],
            [ExerciseFixtures::INCLINE_DB_PRESS,  3, 10, 10, null, 120, null],
            [ExerciseFixtures::LATERAL_RAISE,     4, 12, 12, null,  90, 'Prendre léger, contrôle total de la montée.'],
            [ExerciseFixtures::MILITARY_PRESS,    3, 10, 10, null, 120, null],
            [ExerciseFixtures::DIPS,              3, 12, 12, null,  90, 'Dips lestés ou pushdown poulie selon niveau.'],
            [ExerciseFixtures::CARDIO_LISS,       1, 20, 20, null,   0, 'LISS fin de séance — 60 à 70% FC max.'],
        ];

        $this->addExercises($manager, $session, $exercises);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PULL — Mercredi (dayOfWeek=3)
    // ──────────────────────────────────────────────────────────────────────────
    private function createPullSession(ObjectManager $manager, Program $program): void
    {
        $session = new WorkoutTemplate();
        $session->setProgram($program);
        $session->setName('PULL — Dos / Biceps');
        $session->setDayOfWeek(3); // Mercredi
        $manager->persist($session);

        $exercises = [
            [ExerciseFixtures::RDL,          4, 6,  8,  null, 180, 'Attention au bas du dos. Garder le buste neutre.'],
            [ExerciseFixtures::LAT_PULLDOWN,  4, 8,  10, null, 120, 'Tractions assistées si possible — viser la ROM complète.'],
            [ExerciseFixtures::BARBELL_ROW,   3, 10, 10, null, 120, null],
            [ExerciseFixtures::CABLE_ROW,     3, 12, 12, null,  90, null],
            [ExerciseFixtures::EZ_CURL,       3, 12, 12, null,  90, 'Supination complète en haut.'],
            [ExerciseFixtures::CARDIO_LISS,   1, 20, 20, null,   0, 'LISS fin de séance — 60 à 70% FC max.'],
        ];

        $this->addExercises($manager, $session, $exercises);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LEGS — Vendredi (dayOfWeek=5)
    // ──────────────────────────────────────────────────────────────────────────
    private function createLegsSession(ObjectManager $manager, Program $program): void
    {
        $session = new WorkoutTemplate();
        $session->setProgram($program);
        $session->setName('LEGS — Cuisses / Fessiers / Mollets');
        $session->setDayOfWeek(5); // Vendredi
        $manager->persist($session);

        $exercises = [
            [ExerciseFixtures::SQUAT,      4, 6,  8,  null, 180, 'Squat barre haute ou Hack Squat machine.'],
            [ExerciseFixtures::LEG_PRESS,  3, 10, 10, null, 120, null],
            [ExerciseFixtures::LUNGES,     3, 12, 12, null, 120, '12 reps par côté. Fentes marchées ou alternées.'],
            [ExerciseFixtures::LEG_CURL,   3, 12, 12, null,  90, null],
            [ExerciseFixtures::HIP_THRUST, 3, 15, 15, null, 120, 'Hip thrust barre ou ponte fessiers. Squeeze en haut.'],
            [ExerciseFixtures::CALF_RAISE, 4, 15, 15, null,  60, 'Tempo lent 2-0-2. ROM complète.'],
            [ExerciseFixtures::CARDIO_LISS, 1, 20, 20, null,   0, 'LISS fin de séance — 60 à 70% FC max.'],
        ];

        $this->addExercises($manager, $session, $exercises);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CARDIO DÉDIÉ — Samedi (dayOfWeek=6)
    // ──────────────────────────────────────────────────────────────────────────
    private function createCardioSession(ObjectManager $manager, Program $program): void
    {
        $session = new WorkoutTemplate();
        $session->setProgram($program);
        $session->setName('CARDIO DÉDIÉ — Longue durée');
        $session->setDayOfWeek(6); // Samedi
        $manager->persist($session);

        $exercises = [
            [ExerciseFixtures::CARDIO_LONG, 1, 45, 60, null, 0, 'Vélo, natation, elliptique ou marche rapide. Zone cible: 60-70% FC max (~115-135 bpm).'],
        ];

        $this->addExercises($manager, $session, $exercises);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array<array{0:string, 1:int, 2:int, 3:int, 4:string|null, 5:int, 6:string|null}> $exercises
     */
    private function addExercises(ObjectManager $manager, WorkoutTemplate $session, array $exercises): void
    {
        foreach ($exercises as $position => [$ref, $sets, $repsMin, $repsMax, $weightKg, $rest, $notes]) {
            /** @var Exercise $exercise */
            $exercise = $this->getReference($ref, Exercise::class);

            $et = new ExerciseTemplate();
            $et->setWorkoutTemplate($session);
            $et->setExercise($exercise);
            $et->setPosition($position);
            $et->setTargetSets($sets);
            $et->setTargetRepsMin($repsMin);
            $et->setTargetRepsMax($repsMax);
            $et->setTargetWeightKg($weightKg);
            $et->setRestSeconds($rest > 0 ? $rest : null);
            $et->setNotes($notes);
            $manager->persist($et);
        }
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
