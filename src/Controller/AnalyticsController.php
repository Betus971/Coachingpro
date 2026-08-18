<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\WorkoutSetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/stats', name: 'app_analytics_')]
class AnalyticsController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(WorkoutSetRepository $setRepo, ChartBuilderInterface $chartBuilder, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // 1. Personal Records (PRs)
        $prs = $setRepo->getPersonalRecords($user);

        // 2. Volume PAR EXERCICE (le volume total toutes séances confondues mélange
        //    leg day / arm day et n'a pas de sens — on suit un exercice à la fois).
        $exercises   = $setRepo->getTrainedExercises($user);
        $exerciseIds = array_column($exercises, 'id');

        $selectedId = (string) $request->query->get('exercise', '');
        if ($selectedId === '' || !in_array($selectedId, $exerciseIds, true)) {
            $selectedId = $exercises[0]['id'] ?? null;
        }

        $selectedName = null;
        foreach ($exercises as $e) {
            if ($e['id'] === $selectedId) {
                $selectedName = $e['name'];
                break;
            }
        }

        // Granularité : jour / semaine / mois / année (défaut : jour).
        $period = (string) $request->query->get('period', 'day');
        if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
            $period = 'day';
        }

        $history = $selectedId ? $setRepo->getVolumeHistoryForExercise($user, $selectedId, $period) : [];
        $labels  = array_map(fn ($v) => $this->formatBucketLabel((string) $v['bucket'], $period), $history);
        $volumes = array_map(fn ($v) => (float) $v['total_volume'], $history);
        $weights = array_map(fn ($v) => $v['top_weight'] !== null ? (float) $v['top_weight'] : null, $history);

        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'type'            => 'bar',
                    'label'           => 'Volume (kg)',
                    'backgroundColor' => '#9b6dff',
                    'data'            => $volumes,
                    'yAxisID'         => 'y',
                    'order'           => 2,
                ],
                [
                    'type'            => 'line',
                    'label'           => 'Charge max (kg)',
                    'borderColor'     => '#00c9a7',
                    'backgroundColor' => '#00c9a7',
                    'data'            => $weights,
                    'yAxisID'         => 'y1',
                    'tension'         => 0.3,
                    'spanGaps'        => true,
                    'order'           => 1,
                ],
            ],
        ]);

        $chart->setOptions([
            'maintainAspectRatio' => false,
            'interaction'         => ['mode' => 'index', 'intersect' => false],
            'layout'              => ['padding' => ['top' => 15]],
            'plugins'             => ['legend' => ['labels' => ['padding' => 16]]],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position'    => 'left',
                    'title'       => ['display' => true, 'text' => 'Volume (kg)'],
                ],
                'y1' => [
                    'beginAtZero' => true,
                    'position'    => 'right',
                    'grid'        => ['drawOnChartArea' => false],
                    'title'       => ['display' => true, 'text' => 'Charge max (kg)'],
                ],
            ],
        ]);

        return $this->render('analytics/index.html.twig', [
            'prs'          => $prs,
            'chart'        => $chart,
            'exercises'    => $exercises,
            'selectedId'   => $selectedId,
            'selectedName' => $selectedName,
            'period'       => $period,
        ]);
    }

    /**
     * Formate le libellé d'un bucket selon la granularité.
     *  - day   : '2026-08-17'   → '17/08'
     *  - week  : '2026-S33'     → 'S33 26'
     *  - month : '2026-08'      → '08/26'
     *  - year  : '2026'         → '2026'
     */
    private function formatBucketLabel(string $bucket, string $period): string
    {
        return match ($period) {
            'week' => (function () use ($bucket) {
                [$year, $week] = array_pad(explode('-', $bucket), 2, '');
                return $week . ' ' . substr($year, -2);
            })(),
            'month' => (function () use ($bucket) {
                [$year, $month] = array_pad(explode('-', $bucket), 2, '');
                return $month . '/' . substr($year, -2);
            })(),
            'year' => $bucket,
            default => (new \DateTime($bucket))->format('d/m'),
        };
    }
}
