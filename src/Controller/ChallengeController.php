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
            'active'     => $active,
            'history'    => $history,
            'activeIds'  => $activeIds,
        ]);
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
        $milestones  = [7, 14, 21, $duration];

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

        $milestones = [7, 14, 21, $duration];

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

    private function assertOwner(ChallengeParticipation $participation): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$participation->getUser()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException();
        }
    }
}
