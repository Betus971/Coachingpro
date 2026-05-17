<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSet;
use App\Repository\ExerciseRepository;
use App\Repository\WorkoutSessionRepository;
use App\Repository\WorkoutTemplateRepository;
use App\Service\GeminiCoachService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/seances', name: 'app_session_')]
class WorkoutSessionController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(WorkoutSessionRepository $repo, GeminiCoachService $gemini): Response
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
    public function new(ExerciseRepository $exerciseRepo, WorkoutTemplateRepository $templateRepo): Response
    {
        $exercises = $exerciseRepo->findBy([], ['name' => 'ASC']);

        // Templates du programme actif pour pré-remplir
        /** @var User $user */
        $user = $this->getUser();
        $templates = $templateRepo->findBy([], ['dayOfWeek' => 'ASC']);

        return $this->render('session/new.html.twig', [
            'exercises' => $exercises,
            'templates' => $templates,
        ]);
    }

    #[Route('/new', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, ExerciseRepository $exerciseRepo, WorkoutTemplateRepository $templateRepo): Response
    {
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

        // Sets: tableau d'exercices envoyés depuis le formulaire dynamique
        // Format: sets[0][exercise_id], sets[0][set_number], sets[0][reps], sets[0][weight_kg], sets[0][rpe], sets[0][is_warmup]
        $setsData = $request->request->all('sets');
        foreach ($setsData as $index => $setData) {
            if (empty($setData['exercise_id'])) continue;

            $exercise = $exerciseRepo->find($setData['exercise_id']);
            if (!$exercise) continue;

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

        $em->flush();
        $this->addFlash('success', 'Séance enregistrée ✓');
        return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
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
