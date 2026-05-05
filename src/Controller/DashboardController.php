<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WeightLog;
use App\Repository\NutritionLogRepository;
use App\Repository\WeightLogRepository;
use App\Repository\WorkoutSessionRepository;
use App\Repository\ProgramAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_dashboard')]
class DashboardController extends AbstractController
{
    public function __invoke(
        WeightLogRepository $weightRepo,
        WorkoutSessionRepository $sessionRepo,
        NutritionLogRepository $nutritionRepo,
        ProgramAssignmentRepository $assignmentRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Dernière pesée
        $lastWeight = $weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'DESC']);

        // Pesée de départ (la plus ancienne)
        $startWeight = $weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'ASC']);

        // 5 dernières pesées pour le mini-graphe
        $recentWeights = $weightRepo->findBy(['user' => $user], ['loggedOn' => 'DESC'], 10);

        // 5 dernières séances
        $recentSessions = $sessionRepo->findBy(['user' => $user], ['performedAt' => 'DESC'], 5);

        // Log nutrition aujourd'hui
        $todayNutrition = $nutritionRepo->findOneBy([
            'user'     => $user,
            'loggedOn' => new \DateTimeImmutable('today'),
        ]);

        // Programme actif
        $activeAssignment = $assignmentRepo->findOneBy(['user' => $user, 'isActive' => true]);

        // Calcul progression
        $startKg  = $startWeight ? (float) $startWeight->getWeightKg() : 121.2;
        $currentKg = $lastWeight ? (float) $lastWeight->getWeightKg() : $startKg;
        $targetKg  = 95.0;
        $totalToLose = $startKg - $targetKg;
        $lost        = $startKg - $currentKg;
        $progress    = $totalToLose > 0 ? round(($lost / $totalToLose) * 100, 1) : 0;

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

        return $this->render('dashboard/index.html.twig', [
            'lastWeight'       => $lastWeight,
            'startKg'          => $startKg,
            'currentKg'        => $currentKg,
            'targetKg'         => $targetKg,
            'lost'             => $lost,
            'progress'         => $progress,
            'recentWeights'    => array_reverse($recentWeights),
            'recentSessions'   => $recentSessions,
            'todayNutrition'   => $todayNutrition,
            'todayWorkout'     => $todayWorkout,
            'activeAssignment' => $activeAssignment,
        ]);
    }
}
