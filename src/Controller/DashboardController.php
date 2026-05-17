<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WeightLog;
use App\Repository\NutritionLogRepository;
use App\Repository\WeightLogRepository;
use App\Repository\WorkoutSessionRepository;
use App\Repository\ProgramAssignmentRepository;
use App\Service\GeminiCoachService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/', name: 'app_dashboard')]
class DashboardController extends AbstractController
{
    public function __invoke(
        WeightLogRepository $weightRepo,
        WorkoutSessionRepository $sessionRepo,
        NutritionLogRepository $nutritionRepo,
        ProgramAssignmentRepository $assignmentRepo,
        ChartBuilderInterface $chartBuilder,
        GeminiCoachService $gemini,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // 1. Logique Coach (On récupère les clients mais on ne fait plus de return anticipé)
        $clients = [];
        if ($this->isGranted('ROLE_COACH')) {
            $clients = $user->getClients();
        }

        // 2. Logique Client (Toujours exécutée pour que le coach puisse voir son programme)
        // Dernière pesée
        $lastWeight = $weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'DESC']);

        // Pesée de départ (pour une perte de poids, on prend le poids MAXIMUM enregistré, car les dates peuvent être faussées par les imports)
        $startWeight = $weightRepo->findOneBy(['user' => $user], ['weightKg' => 'DESC']);

        // Dernières pesées pour le graphe (limite 10)
        $recentWeights = $weightRepo->findBy(['user' => $user], ['loggedOn' => 'DESC'], 10);
        
        // Inverser pour l'ordre chronologique (gauche à droite)
        $orderedWeights = array_reverse($recentWeights);

        // 5 dernières séances
        $recentSessions = $sessionRepo->findBy(['user' => $user], ['performedAt' => 'DESC'], 5);

        // Log nutrition aujourd'hui
        $todayNutrition = $nutritionRepo->findOneBy([
            'user'     => $user,
            'loggedOn' => new \DateTimeImmutable('today'),
        ]);

        // Programme actif
        $activeAssignment = $assignmentRepo->findOneBy(['user' => $user, 'isActive' => true]);

        // --- CORRECTION DU CALCUL DE PROGRESSION ---
        $startKg  = $startWeight ? (float) $startWeight->getWeightKg() : 121.2;
        $currentKg = $lastWeight ? (float) $lastWeight->getWeightKg() : $startKg;
        $targetKg  = 95.0; // Poids cible

        // On utilise max() pour éviter les nombres négatifs
        $kilosPerdus = max(0, $startKg - $currentKg);
        $kilosRestants = max(0, $currentKg - $targetKg);
        $totalToLose = max(0.1, $startKg - $targetKg); // Eviter division par zéro

        // Vrai pourcentage entre 0 et 100
        $rawProgress = ($kilosPerdus / $totalToLose) * 100;
        $progress = max(0, min(100, round($rawProgress)));

        // --- CHART.JS ---
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => array_map(fn($w) => $w->getLoggedOn()->format('d/m'), $orderedWeights),
            'datasets' => [
                [
                    'label' => 'Évolution du poids (kg)',
                    'backgroundColor' => '#f97316',
                    'borderColor' => '#f97316',
                    'data' => array_map(fn($w) => $w->getWeightKg(), $orderedWeights),
                    'tension' => 0.4, // Courbe adoucie
                ],
            ],
        ]);

        // Séance d'aujourd'hui selon le programme
        $todayWorkout = null;
        if ($activeAssignment) {
            $dayOfWeek = (int) (new \DateTimeImmutable())->format('N'); // 1=Lundi ... 7=Dimanche
            foreach ($activeAssignment->getProgram()->getWorkoutTemplates() as $template) {
                if ($template->getDayOfWeek() === $dayOfWeek) {
                    $todayWorkout = $template;
                    break;
                }
            }
        }

        // Si l'utilisateur est un coach, on affiche la vue à onglets, sinon la vue client normale
        $template = $this->isGranted('ROLE_COACH') ? 'dashboard/coach_tabs.html.twig' : 'dashboard/client.html.twig';

        $coachAdvice = $gemini->getDashboardAdvice($user);

        return $this->render($template, [
            'clients'          => $clients,
            'lastWeight'       => $lastWeight,
            'startKg'          => $startKg,
            'currentKg'        => $currentKg,
            'targetKg'         => $targetKg,
            'kilosPerdus'      => $kilosPerdus,
            'kilosRestants'    => $kilosRestants,
            'progress'         => $progress,
            'chart'            => $chart,
            'recentSessions'   => $recentSessions,
            'todayNutrition'   => $todayNutrition,
            'todayWorkout'     => $todayWorkout,
            'activeAssignment' => $activeAssignment,
            'coachAdvice'      => $coachAdvice,
        ]);
    }
}
