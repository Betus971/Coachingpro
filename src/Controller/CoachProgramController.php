<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ExerciseTemplate;
use App\Entity\Program;
use App\Entity\User;
use App\Entity\WorkoutTemplate;
use App\Repository\ExerciseRepository;
use App\Repository\WorkoutTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Édition par le coach d'un programme (typiquement le clone assigné à un client) :
 * jours (WorkoutTemplate) et exercices (ExerciseTemplate).
 *
 * Sécurité : tout passe par le Voter EDIT sur le Program (createdBy = coach) + CSRF.
 */
#[Route('/coach')]
#[IsGranted(User::ROLE_COACH)]
class CoachProgramController extends AbstractController
{
    private const UUID = '[0-9a-fA-F-]{36}';

    #[Route('/programmes/{id}', name: 'app_coach_program_edit', methods: ['GET'], requirements: ['id' => self::UUID])]
    public function edit(Program $program, ExerciseRepository $exerciseRepo): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $program);

        return $this->render('coach/program_edit.html.twig', [
            'program'   => $program,
            'exercises' => $exerciseRepo->findBy([], ['name' => 'ASC']),
            'dayNames'  => [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'],
        ]);
    }

    #[Route('/programmes/{id}/jour', name: 'app_coach_program_add_day', methods: ['POST'], requirements: ['id' => self::UUID])]
    public function addDay(Program $program, Request $request, EntityManagerInterface $em): Response
    {
        $this->guard($program, $request, 'prog' . $program->getId());

        $day = $request->request->get('day_of_week');
        $template = (new WorkoutTemplate())
            ->setName(trim((string) $request->request->get('name')) ?: 'Nouveau jour')
            ->setDayOfWeek($day !== '' && $day !== null ? (int) $day : null)
            ->setProgram($program);

        $em->persist($template);
        $em->flush();
        $this->addFlash('success', 'Jour ajouté.');

        return $this->toEditor($program);
    }

    #[Route('/jour-template/{id}/supprimer', name: 'app_coach_program_delete_day', methods: ['POST'], requirements: ['id' => self::UUID])]
    public function deleteDay(WorkoutTemplate $template, Request $request, EntityManagerInterface $em): Response
    {
        $program = $template->getProgram();
        $this->guard($program, $request, 'wt' . $template->getId());

        $em->remove($template);
        $em->flush();
        $this->addFlash('success', 'Jour supprimé.');

        return $this->toEditor($program);
    }

    #[Route('/programmes/{id}/exercice', name: 'app_coach_program_add_exercise', methods: ['POST'], requirements: ['id' => self::UUID])]
    public function addExercise(
        Program $program,
        Request $request,
        WorkoutTemplateRepository $templateRepo,
        ExerciseRepository $exerciseRepo,
        EntityManagerInterface $em,
    ): Response {
        $this->guard($program, $request, 'prog' . $program->getId());

        $template = $templateRepo->find((string) $request->request->get('template_id'));
        if (!$template || !$template->getProgram()->getId()->equals($program->getId())) {
            $this->addFlash('error', 'Jour introuvable.');
            return $this->toEditor($program);
        }

        $exercise = $exerciseRepo->find((string) $request->request->get('exercise_id'));
        if (!$exercise) {
            $name = trim((string) $request->request->get('exercise_name'));
            $exercise = $name !== '' ? $exerciseRepo->findOneBy(['name' => $name]) : null;
        }
        if (!$exercise) {
            $this->addFlash('error', 'Exercice introuvable — sélectionne-le dans la liste.');
            return $this->toEditor($program);
        }

        $position = 0;
        foreach ($template->getExerciseTemplates() as $existing) {
            $position = max($position, $existing->getPosition() + 1);
        }

        $et = (new ExerciseTemplate())
            ->setWorkoutTemplate($template)
            ->setExercise($exercise)
            ->setPosition($position)
            ->setTargetSets(max(1, (int) $request->request->get('target_sets', 3)))
            ->setTargetRepsMin(max(1, (int) $request->request->get('target_reps_min', 8)))
            ->setTargetRepsMax(max(1, (int) $request->request->get('target_reps_max', 12)))
            ->setTargetWeightKg($this->decOrNull($request->request->get('target_weight')))
            ->setRestSeconds($this->intOrNull($request->request->get('rest_seconds')));

        $em->persist($et);
        $em->flush();
        $this->addFlash('success', sprintf('%s ajouté.', $exercise->getName()));

        return $this->toEditor($program);
    }

    #[Route('/exercice-template/{id}', name: 'app_coach_program_update_exercise', methods: ['POST'], requirements: ['id' => self::UUID])]
    public function updateExercise(ExerciseTemplate $et, Request $request, EntityManagerInterface $em): Response
    {
        $program = $et->getWorkoutTemplate()->getProgram();
        $this->guard($program, $request, 'et' . $et->getId());

        $et->setTargetSets(max(1, (int) $request->request->get('target_sets', $et->getTargetSets())))
            ->setTargetRepsMin(max(1, (int) $request->request->get('target_reps_min', $et->getTargetRepsMin())))
            ->setTargetRepsMax(max(1, (int) $request->request->get('target_reps_max', $et->getTargetRepsMax())))
            ->setTargetWeightKg($this->decOrNull($request->request->get('target_weight')))
            ->setRestSeconds($this->intOrNull($request->request->get('rest_seconds')))
            ->setNotes(trim((string) $request->request->get('notes')) ?: null);

        $em->flush();
        $this->addFlash('success', 'Exercice mis à jour.');

        return $this->toEditor($program);
    }

    #[Route('/exercice-template/{id}/supprimer', name: 'app_coach_program_delete_exercise', methods: ['POST'], requirements: ['id' => self::UUID])]
    public function deleteExercise(ExerciseTemplate $et, Request $request, EntityManagerInterface $em): Response
    {
        $program = $et->getWorkoutTemplate()->getProgram();
        $this->guard($program, $request, 'et' . $et->getId());

        $em->remove($et);
        $em->flush();
        $this->addFlash('success', 'Exercice retiré.');

        return $this->toEditor($program);
    }

    #[Route('/exercice-template/{id}/deplacer', name: 'app_coach_program_move_exercise', methods: ['POST'], requirements: ['id' => self::UUID])]
    public function moveExercise(ExerciseTemplate $et, Request $request, EntityManagerInterface $em): Response
    {
        $program = $et->getWorkoutTemplate()->getProgram();
        $this->guard($program, $request, 'et' . $et->getId());

        $siblings = array_values($et->getWorkoutTemplate()->getExerciseTemplates()->toArray());
        $idx = array_search($et, $siblings, true);
        $target = $request->request->get('direction') === 'up' ? ($siblings[$idx - 1] ?? null) : ($siblings[$idx + 1] ?? null);

        if ($target instanceof ExerciseTemplate) {
            $p = $et->getPosition();
            $et->setPosition($target->getPosition());
            $target->setPosition($p);
            $em->flush();
        }

        return $this->toEditor($program);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function guard(Program $program, Request $request, string $csrfId): void
    {
        $this->denyAccessUnlessGranted('EDIT', $program);
        if (!$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
    }

    private function toEditor(Program $program): Response
    {
        return $this->redirectToRoute('app_coach_program_edit', ['id' => $program->getId()]);
    }

    private function decOrNull(?string $v): ?string
    {
        return $v !== null && trim($v) !== '' ? number_format((float) $v, 2, '.', '') : null;
    }

    private function intOrNull(?string $v): ?int
    {
        return $v !== null && trim($v) !== '' ? (int) $v : null;
    }
}
