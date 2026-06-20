<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ExerciseTemplate;
use App\Entity\Program;
use App\Entity\ProgramAssignment;
use App\Entity\User;
use App\Entity\WorkoutTemplate;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Assignation d'un programme à un client par son coach.
 *
 * - assign() : désactive l'assignation active existante puis en crée une nouvelle.
 * - duplicate() : clone profond (programme + séances types + exercices types) pour
 *   que les retouches faites pour un client n'impactent pas le modèle d'origine.
 */
final readonly class ProgramAssigner
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function assign(Program $program, User $client, bool $duplicate = false): ProgramAssignment
    {
        if ($duplicate) {
            $program = $this->duplicate($program, $program->getCreatedBy());
        }

        // Désactive l'assignation active courante du client.
        $current = $this->em->getRepository(ProgramAssignment::class)
            ->findBy(['user' => $client, 'isActive' => true]);
        foreach ($current as $assignment) {
            $assignment->setIsActive(false);
            $assignment->setEndDate(new \DateTimeImmutable('today'));
        }

        $new = (new ProgramAssignment())
            ->setProgram($program)
            ->setUser($client)
            ->setStartDate(new \DateTimeImmutable('today'))
            ->setIsActive(true);

        $this->em->persist($new);
        $this->em->flush();

        return $new;
    }

    public function duplicate(Program $source, User $owner): Program
    {
        $copy = (new Program())
            ->setName($source->getName() . ' (copie)')
            ->setDescription($source->getDescription())
            ->setDurationWeeks($source->getDurationWeeks())
            ->setCreatedBy($owner);
        $this->em->persist($copy);

        foreach ($source->getWorkoutTemplates() as $template) {
            $templateCopy = (new WorkoutTemplate())
                ->setName($template->getName())
                ->setDayOfWeek($template->getDayOfWeek())
                ->setProgram($copy);
            $this->em->persist($templateCopy);

            foreach ($template->getExerciseTemplates() as $et) {
                $etCopy = (new ExerciseTemplate())
                    ->setExercise($et->getExercise())
                    ->setPosition($et->getPosition())
                    ->setTargetSets($et->getTargetSets())
                    ->setTargetRepsMin($et->getTargetRepsMin())
                    ->setTargetRepsMax($et->getTargetRepsMax())
                    ->setTargetWeightKg($et->getTargetWeightKg())
                    ->setRestSeconds($et->getRestSeconds())
                    ->setNotes($et->getNotes())
                    ->setWorkoutTemplate($templateCopy);
                $this->em->persist($etCopy);
            }
        }

        return $copy;
    }
}
