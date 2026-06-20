<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ActivityLevel;
use App\Repository\GoalRepository;
use App\Repository\ProgramAssignmentRepository;
use App\Service\Nutrition\NutritionCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/programme', name: 'app_program_show')]
class ProgramController extends AbstractController
{
    public function __invoke(Request $request, ProgramAssignmentRepository $assignmentRepo, \App\Repository\WeightLogRepository $weightRepo, GoalRepository $goalRepo, NutritionCalculator $nutritionCalculator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $goals = $goalRepo->findOpenForUser($user);
        $activeGoal = $goals[0] ?? null;

        $assignments = $assignmentRepo->findBy(['user' => $user], ['startDate' => 'DESC']);
        $active = null;
        foreach ($assignments as $a) {
            if ($a->isActive()) { $active = $a; break; }
        }

        // Pesée de départ (poids max) pour calculer les jalons
        $startWeightLog = $weightRepo->findOneBy(['user' => $user], ['weightKg' => 'DESC']);
        $startKg = $activeGoal ? (float) $activeGoal->getStartValue() : ($startWeightLog ? (float) $startWeightLog->getWeightKg() : 0.0);
        $targetKg = $activeGoal ? (float) $activeGoal->getTargetValue() : null;

        // Pesée actuelle (la plus récente)
        $currentWeightLog = $weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'DESC']);
        $currentKg = $currentWeightLog ? (float) $currentWeightLog->getWeightKg() : $startKg;

        // Jours semaine ISO
        $dayNames = [1 => 'LUNDI', 2 => 'MARDI', 3 => 'MERCREDI', 4 => 'JEUDI', 5 => 'VENDREDI', 6 => 'SAMEDI', 7 => 'DIMANCHE'];

        // Calcul nutritionnel (BMR/TDEE/macros). Override d'activité via ?activity=...
        $override = ActivityLevel::tryFrom((string) $request->query->get('activity', ''));
        $nutritionPlan = $nutritionCalculator->compute($user, $override);

        return $this->render('program/show.html.twig', [
            'assignment'     => $active,
            'activeGoal'     => $activeGoal,
            'dayNames'       => $dayNames,
            'startKg'        => $startKg,
            'targetKg'       => $targetKg,
            'currentKg'      => $currentKg,
            'nutritionPlan'  => $nutritionPlan,
            'activityLevels' => ActivityLevel::cases(),
        ]);
    }
}
