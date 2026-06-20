<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClientInvitation;
use App\Entity\User;
use App\Repository\ClientInvitationRepository;
use App\Repository\GoalRepository;
use App\Repository\NutritionLogRepository;
use App\Repository\WeightLogRepository;
use App\Repository\WorkoutSessionRepository;
use App\Service\InvitationMailer;
use App\Service\Nutrition\NutritionCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Espace coach : gestion de ses clients et des invitations.
 */
#[Route('/coach')]
#[IsGranted(User::ROLE_COACH)]
class CoachClientController extends AbstractController
{
    #[Route('/clients', name: 'app_coach_clients', methods: ['GET'])]
    public function clients(ClientInvitationRepository $invitations): Response
    {
        /** @var User $coach */
        $coach = $this->getUser();

        return $this->render('coach/clients.html.twig', [
            'clients'     => $coach->getClients(),
            'invitations' => $invitations->findPendingForCoach($coach),
        ]);
    }

    #[Route('/clients/{id}', name: 'app_coach_client_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function show(
        User $client,
        WeightLogRepository $weightRepo,
        WorkoutSessionRepository $sessionRepo,
        NutritionLogRepository $nutritionRepo,
        GoalRepository $goalRepo,
        NutritionCalculator $nutritionCalculator,
        ChartBuilderInterface $chartBuilder,
    ): Response {
        // Le Voter autorise un coach à voir UNIQUEMENT ses propres clients.
        $this->denyAccessUnlessGranted('VIEW', $client);

        $activeGoal    = $goalRepo->findOpenForUser($client)[0] ?? null;
        $lastWeight    = $weightRepo->findOneBy(['user' => $client], ['loggedOn' => 'DESC']);
        $startWeight   = $weightRepo->findOneBy(['user' => $client], ['weightKg' => 'DESC']);
        $recentWeights = array_reverse($weightRepo->findBy(['user' => $client], ['loggedOn' => 'DESC'], 12));
        $history       = $weightRepo->findBy(['user' => $client], ['loggedOn' => 'DESC'], 8);
        $sessions      = $sessionRepo->findBy(['user' => $client], ['performedAt' => 'DESC'], 6);
        $nutrition     = $nutritionRepo->findBy(['user' => $client], ['loggedOn' => 'DESC'], 5);
        $nutritionPlan = $nutritionCalculator->compute($client);

        // Progression, sans aucune valeur en dur (null si données insuffisantes).
        $startKg   = $activeGoal ? (float) $activeGoal->getStartValue() : ($startWeight ? (float) $startWeight->getWeightKg() : null);
        $targetKg  = $activeGoal ? (float) $activeGoal->getTargetValue() : null;
        $currentKg = $lastWeight ? (float) $lastWeight->getWeightKg() : $startKg;

        $progress = null;
        if ($startKg !== null && $targetKg !== null && $currentKg !== null && abs($startKg - $targetKg) > 0.01) {
            $progress = (int) max(0, min(100, round((($startKg - $currentKg) / ($startKg - $targetKg)) * 100)));
        }

        $chart = null;
        if (count($recentWeights) > 0) {
            $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
            $chart->setData([
                'labels' => array_map(fn ($w) => $w->getLoggedOn()->format('d/m'), $recentWeights),
                'datasets' => [[
                    'label'           => 'Poids (kg)',
                    'backgroundColor' => '#f97316',
                    'borderColor'     => '#f97316',
                    'data'            => array_map(fn ($w) => $w->getWeightKg(), $recentWeights),
                    'tension'         => 0.4,
                ]],
            ]);
            $chart->setOptions([
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
            ]);
        }

        return $this->render('coach/client_show.html.twig', [
            'client'        => $client,
            'activeGoal'    => $activeGoal,
            'startKg'       => $startKg,
            'targetKg'      => $targetKg,
            'currentKg'     => $currentKg,
            'progress'      => $progress,
            'history'       => $history,
            'sessions'      => $sessions,
            'nutrition'     => $nutrition,
            'nutritionPlan' => $nutritionPlan,
            'chart'         => $chart,
        ]);
    }

    #[Route('/clients/inviter', name: 'app_coach_client_invite', methods: ['GET', 'POST'])]
    public function invite(
        Request $request,
        EntityManagerInterface $em,
        InvitationMailer $mailer,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();
        $error = null;

        if ($request->isMethod('POST')) {
            $email     = trim((string) $request->request->get('email', ''));
            $firstName = trim((string) $request->request->get('first_name', '')) ?: null;
            $lastName  = trim((string) $request->request->get('last_name', '')) ?: null;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse email invalide.';
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = 'Un compte existe déjà avec cet email.';
            } else {
                $invitation = (new ClientInvitation())
                    ->setCoach($coach)
                    ->setEmail($email)
                    ->setFirstName($firstName)
                    ->setLastName($lastName);

                $em->persist($invitation);
                $em->flush();

                $sent = $mailer->send($invitation);
                $this->addFlash(
                    'success',
                    $sent
                        ? sprintf('Invitation envoyée à %s.', $email)
                        : sprintf('Invitation créée pour %s. L\'email n\'a pas pu être envoyé (transport non configuré) — copie le lien ci-dessous.', $email),
                );

                return $this->redirectToRoute('app_coach_clients');
            }
        }

        return $this->render('coach/invite.html.twig', ['error' => $error]);
    }

    #[Route('/invitations/{id}/revoquer', name: 'app_coach_invite_revoke', methods: ['POST'])]
    public function revoke(
        ClientInvitation $invitation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();

        if (!$invitation->getCoach()->getId()->equals($coach->getId())) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('revoke' . $invitation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $invitation->revoke();
        $em->flush();
        $this->addFlash('success', 'Invitation révoquée.');

        return $this->redirectToRoute('app_coach_clients');
    }
}
