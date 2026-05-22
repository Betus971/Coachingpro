<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\WorkoutSetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/stats', name: 'app_analytics_')]
class AnalyticsController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(WorkoutSetRepository $setRepo, ChartBuilderInterface $chartBuilder): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // 1. Personal Records (PRs)
        $prs = $setRepo->getPersonalRecords($user);

        // 2. Historique du volume
        $volumeHistory = $setRepo->getVolumeHistory($user);

        // Chart.js pour le volume
        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        
        $labels = array_map(fn($v) => (new \DateTime($v['date']))->format('d/m'), $volumeHistory);
        $data = array_map(fn($v) => $v['total_volume'], $volumeHistory);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Volume Total Soulevé (kg)',
                    'backgroundColor' => '#9b6dff', // Couleur accent
                    'data' => $data,
                ],
            ],
        ]);
        
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'layout' => [
                'padding' => [
                    'top' => 15,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'padding' => 20,
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ]
            ]
        ]);

        return $this->render('analytics/index.html.twig', [
            'prs' => $prs,
            'chart' => $chart,
        ]);
    }
}
