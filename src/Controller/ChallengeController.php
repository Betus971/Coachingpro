<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Challenge;
use App\Entity\ChallengeCheckIn;
use App\Entity\ChallengeParticipation;
use App\Entity\User;
use App\Repository\ChallengeCheckInRepository;
use App\Repository\ChallengeParticipationRepository;
use App\Repository\ChallengeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
#[Route('/defis', name: 'app_challenge_')]
class ChallengeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ChallengeRepository $challengeRepo,
        ChallengeParticipationRepository $participationRepo,
    ): Response {
        /** @var User $user */
        $user    = $this->getUser();
        $active  = $participationRepo->findActiveForUser($user);
        $history = $participationRepo->findHistoryForUser($user);

        $activeIds = array_map(
            fn(ChallengeParticipation $p) => $p->getChallenge()->getId()->toRfc4122(),
            $active,
        );

        return $this->render('challenge/index.html.twig', [
            'challenges' => $challengeRepo->findBy(['isPreset' => true], ['category' => 'ASC']),
            'custom'     => $challengeRepo->findCustomForUser($user),
            'active'     => $active,
            'history'    => $history,
            'activeIds'  => $activeIds,
        ]);
    }

    #[Route('/creer', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        ChallengeParticipationRepository $participationRepo,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $title    = trim((string) $request->request->get('title', ''));
        $emoji    = trim((string) $request->request->get('emoji', '🎯'));
        $desc     = trim((string) $request->request->get('description', ''));
        $duration = max(1, min(365, (int) $request->request->get('duration', 30)));
        $category = $request->request->get('category', 'lifestyle');

        if ($title === '') {
            $this->addFlash('error', 'Le titre du défi est obligatoire.');
            return $this->redirectToRoute('app_challenge_index');
        }

        if (!in_array($category, ['nutrition', 'fitness', 'lifestyle', 'mindset'], true)) {
            $category = 'lifestyle';
        }

        $challenge = (new Challenge())
            ->setTitle($title)
            ->setEmoji($emoji ?: '🎯')
            ->setDescription($desc ?: "Défi personnel : $title")
            ->setDurationDays($duration)
            ->setCategory($category)
            ->setIsPreset(false)
            ->setCreatedBy($user);

        $participation = (new ChallengeParticipation())
            ->setUser($user)
            ->setChallenge($challenge);

        $em->persist($challenge);
        $em->persist($participation);
        $em->flush();

        $this->addFlash('success', "Défi créé ! C'est parti pour {$duration} jours 🔥");
        return $this->redirectToRoute('app_challenge_show', ['id' => $participation->getId()]);
    }

    #[Route('/participation/{id}/supprimer-defi', name: 'delete_custom', methods: ['POST'])]
    public function deleteCustomChallenge(
        ChallengeParticipation $participation,
        EntityManagerInterface $em,
    ): Response {
        $this->assertOwner($participation);

        $challenge = $participation->getChallenge();
        if ($challenge->isPreset()) {
            throw $this->createAccessDeniedException('Les défis système ne peuvent pas être supprimés.');
        }

        $em->remove($challenge); // cascade supprime participation + check-ins
        $em->flush();

        $this->addFlash('success', 'Défi personnel supprimé.');
        return $this->redirectToRoute('app_challenge_index');
    }

    #[Route('/{id}/rejoindre', name: 'join', methods: ['POST'])]
    public function join(
        Challenge $challenge,
        ChallengeParticipationRepository $participationRepo,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($participationRepo->findForUserAndChallenge($user, $challenge) !== null) {
            $this->addFlash('info', 'Tu participes déjà à ce défi !');
            return $this->redirectToRoute('app_challenge_index');
        }

        $participation = (new ChallengeParticipation())
            ->setUser($user)
            ->setChallenge($challenge);
        $em->persist($participation);
        $em->flush();

        $this->addFlash('success', "Défi lancé ! C'est parti pour {$challenge->getDurationDays()} jours 🔥");
        return $this->redirectToRoute('app_challenge_show', ['id' => $participation->getId()]);
    }

    #[Route('/participation/{id}', name: 'show', methods: ['GET'])]
    public function show(ChallengeParticipation $participation): Response
    {
        $this->assertOwner($participation);

        $checkedSet  = array_flip($participation->getCheckedDays());
        $daysElapsed = $participation->getDaysElapsed();
        $duration    = $participation->getChallenge()->getDurationDays();
        $milestones  = $this->computeMilestones($duration);

        $days = [];
        for ($d = 1; $d <= $duration; $d++) {
            $days[$d] = [
                'checked'     => isset($checkedSet[$d]),
                'accessible'  => $d <= $daysElapsed,
                'today'       => $d === $daysElapsed && $participation->isActive(),
                'milestone'   => in_array($d, $milestones, true),
            ];
        }

        return $this->render('challenge/show.html.twig', [
            'participation' => $participation,
            'days'          => $days,
            'milestones'    => $milestones,
            'streak'        => $participation->getCurrentStreak(),
            'progress'      => $participation->getProgressPercent(),
            'checkedCount'  => count($participation->getCheckIns()),
        ]);
    }

    #[Route('/participation/{id}/check/{day}', name: 'check_day', methods: ['POST'])]
    public function checkDay(
        ChallengeParticipation $participation,
        int $day,
        ChallengeCheckInRepository $checkInRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->assertOwner($participation);

        if (!$participation->isActive()) {
            return $this->json(['error' => 'Défi terminé ou abandonné'], 400);
        }

        $duration    = $participation->getChallenge()->getDurationDays();
        $daysElapsed = $participation->getDaysElapsed();

        if ($day < 1 || $day > $duration || $day > $daysElapsed) {
            return $this->json(['error' => 'Jour invalide'], 400);
        }

        $existing = $checkInRepo->findForDay($participation, $day);

        if ($existing) {
            $em->remove($existing);
            $em->flush();
            $em->refresh($participation);
            $checked = false;
        } else {
            $checkIn = (new ChallengeCheckIn())
                ->setParticipation($participation)
                ->setDayNumber($day);
            $em->persist($checkIn);
            $em->flush();
            $em->refresh($participation);

            if (count($participation->getCheckIns()) >= $duration) {
                $participation->setStatus(ChallengeParticipation::STATUS_COMPLETED);
                $em->flush();
            }

            $checked = true;
        }

        $milestones = $this->computeMilestones($duration);

        return $this->json([
            'checked'     => $checked,
            'total'       => count($participation->getCheckIns()),
            'streak'      => $participation->getCurrentStreak(),
            'progress'    => $participation->getProgressPercent(),
            'isMilestone' => in_array($day, $milestones, true) && $checked,
            'isCompleted' => $participation->getStatus() === ChallengeParticipation::STATUS_COMPLETED,
            'milestoneDay' => $day,
        ]);
    }

    #[Route('/participation/{id}/abandonner', name: 'abandon', methods: ['POST'])]
    public function abandon(
        ChallengeParticipation $participation,
        EntityManagerInterface $em,
    ): Response {
        $this->assertOwner($participation);

        $participation->setStatus(ChallengeParticipation::STATUS_ABANDONED);
        $em->flush();

        $this->addFlash('info', 'Défi abandonné. Tu peux toujours en recommencer un !');
        return $this->redirectToRoute('app_challenge_index');
    }

    /** Milestones proportionnels (25 %, 50 %, 75 %, 100 %), dédoublonnés. */
    private function computeMilestones(int $duration): array
    {
        $raw = [
            (int) round($duration * 0.25),
            (int) round($duration * 0.50),
            (int) round($duration * 0.75),
            $duration,
        ];
        return array_values(array_unique(array_filter($raw, fn($m) => $m >= 1)));
    }

    private function assertOwner(ChallengeParticipation $participation): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$participation->getUser()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException();
        }
    }
}
