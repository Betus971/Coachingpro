<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSet;
use App\Repository\ExerciseRepository;
use App\Repository\FavoriteExerciseRepository;
use App\Repository\WorkoutSessionRepository;
use App\Repository\WorkoutTemplateRepository;
use App\Entity\WorkoutTemplate;
use App\Service\MistralCoachService;
use App\Service\GamificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/seances', name: 'app_session_')]
class WorkoutSessionController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(WorkoutSessionRepository $repo, MistralCoachService $gemini): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $sessions = $repo->findBy(['user' => $user], ['performedAt' => 'DESC'], 20);

        return $this->render('session/index.html.twig', [
            'sessions'    => $sessions,
            'coachAdvice' => $gemini->getSessionAdvice($user),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET'])]
    public function new(ExerciseRepository $exerciseRepo, WorkoutTemplateRepository $templateRepo, FavoriteExerciseRepository $favRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('session/new.html.twig', [
            'exercises' => $exerciseRepo->findBy([], ['name' => 'ASC']),
            'templates' => $templateRepo->findBy([], ['dayOfWeek' => 'ASC']),
            'favorites' => $favRepo->findExercisesForUser($user),
            'recents'   => $exerciseRepo->findMostUsedForUser($user, 8),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ExerciseRepository $exerciseRepo,
        WorkoutTemplateRepository $templateRepo,
        GamificationService $gamification
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $session = new WorkoutSession();
        $session->setUser($user);
        $session->setName($request->request->get('name', 'Séance'));
        $session->setPerformedAt(new \DateTimeImmutable($request->request->get('performed_at', 'now')));
        $session->setDurationMinutes($request->request->get('duration_minutes') !== '' ? (int) $request->request->get('duration_minutes') : null);
        $session->setRpe($request->request->get('rpe') !== '' ? (int) $request->request->get('rpe') : null);
        $session->setNotes($request->request->get('notes'));

        // Lien optionnel vers un template source
        if ($templateId = $request->request->get('source_template_id')) {
            $template = $templateRepo->find($templateId);
            $session->setSourceTemplate($template);
        }

        $em->persist($session);

        $this->hydrateSets($session, $request, $exerciseRepo, $em);

        $em->flush();

        $gamificationStatus = $gamification->updateStreak($user);
        if ($gamificationStatus['streak_updated'] && $gamificationStatus['message']) {
            $this->addFlash('success', $gamificationStatus['message']);
        }

        $this->addFlash('success', 'Séance enregistrée ✓');
        return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET'])]
    public function edit(WorkoutSession $session, ExerciseRepository $exerciseRepo, WorkoutTemplateRepository $templateRepo, FavoriteExerciseRepository $favRepo): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $session);
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('session/new.html.twig', [
            'session'   => $session,
            'exercises' => $exerciseRepo->findBy([], ['name' => 'ASC']),
            'templates' => $templateRepo->findBy([], ['dayOfWeek' => 'ASC']),
            'favorites' => $favRepo->findExercisesForUser($user),
            'recents'   => $exerciseRepo->findMostUsedForUser($user, 8),
        ]);
    }

    #[Route('/{id}/edit', name: 'update', methods: ['POST'])]
    public function update(
        WorkoutSession $session,
        Request $request,
        EntityManagerInterface $em,
        ExerciseRepository $exerciseRepo,
        WorkoutTemplateRepository $templateRepo,
    ): Response {
        $this->denyAccessUnlessGranted('EDIT', $session);

        $session->setName($request->request->get('name', 'Séance'));
        $session->setPerformedAt(new \DateTimeImmutable($request->request->get('performed_at', 'now')));
        $session->setDurationMinutes($request->request->get('duration_minutes') !== '' ? (int) $request->request->get('duration_minutes') : null);
        $session->setRpe($request->request->get('rpe') !== '' ? (int) $request->request->get('rpe') : null);
        $session->setNotes($request->request->get('notes'));

        $templateId = $request->request->get('source_template_id');
        $session->setSourceTemplate($templateId ? $templateRepo->find($templateId) : null);

        // Remplace les séries : on supprime les anciennes, on recrée depuis le formulaire.
        foreach ($session->getSets()->toArray() as $old) {
            $session->getSets()->removeElement($old);
            $em->remove($old);
        }
        $this->hydrateSets($session, $request, $exerciseRepo, $em);

        $em->flush();

        $this->addFlash('success', 'Séance mise à jour ✓');
        return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
    }

    /**
     * Crée les WorkoutSet à partir du formulaire dynamique.
     * Format : sets[i][exercise_id|exercise_position|set_number|reps|weight_kg|rpe|is_warmup].
     */
    private function hydrateSets(WorkoutSession $session, Request $request, ExerciseRepository $exerciseRepo, EntityManagerInterface $em): void
    {
        foreach ($request->request->all('sets') as $index => $setData) {
            if (empty($setData['exercise_id'])) {
                continue;
            }
            $exercise = $exerciseRepo->find($setData['exercise_id']);
            if (!$exercise) {
                continue;
            }
            $set = new WorkoutSet();
            $set->setSession($session);
            $set->setExercise($exercise);
            $set->setExercisePosition((int) ($setData['exercise_position'] ?? $index));
            $set->setSetNumber((int) ($setData['set_number'] ?? 1));
            $set->setReps((int) ($setData['reps'] ?? 0));
            $set->setWeightKg(isset($setData['weight_kg']) && $setData['weight_kg'] !== '' ? $setData['weight_kg'] : null);
            $set->setRpe(isset($setData['rpe']) && $setData['rpe'] !== '' ? (int) $setData['rpe'] : null);
            $set->setIsWarmup(!empty($setData['is_warmup']));
            $em->persist($set);
        }
    }

    #[Route('/play/{id}', name: 'play', methods: ['GET'])]
    public function play(WorkoutTemplate $template): Response
    {
        return $this->render('session/play.html.twig', [
            'template' => $template,
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(WorkoutSession $session): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$session->getUser()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException();
        }

        // Grouper les sets par exercice
        $setsByExercise = [];
        foreach ($session->getSets() as $set) {
            $name = $set->getExercise()->getName();
            $setsByExercise[$name][] = $set;
        }

        return $this->render('session/show.html.twig', [
            'session'         => $session,
            'setsByExercise'  => $setsByExercise,
        ]);
    }
}
