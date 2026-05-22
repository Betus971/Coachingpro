<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProgramAssignmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/programme', name: 'app_program_show')]
class ProgramController extends AbstractController
{
    public function __invoke(ProgramAssignmentRepository $assignmentRepo, \App\Repository\WeightLogRepository $weightRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $assignments = $assignmentRepo->findBy(['user' => $user], ['startDate' => 'DESC']);
        $active = null;
        foreach ($assignments as $a) {
            if ($a->isActive()) { $active = $a; break; }
        }

        // Pesée de départ (poids max) pour calculer les jalons
        $startWeightLog = $weightRepo->findOneBy(['user' => $user], ['weightKg' => 'DESC']);
        $startKg = $startWeightLog ? (float) $startWeightLog->getWeightKg() : 121.2;

        // Pesée actuelle (la plus récente)
        $currentWeightLog = $weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'DESC']);
        $currentKg = $currentWeightLog ? (float) $currentWeightLog->getWeightKg() : $startKg;

        // Jours semaine ISO
        $dayNames = [1 => 'LUNDI', 2 => 'MARDI', 3 => 'MERCREDI', 4 => 'JEUDI', 5 => 'VENDREDI', 6 => 'SAMEDI', 7 => 'DIMANCHE'];

        return $this->render('program/show.html.twig', [
            'assignment' => $active,
            'dayNames'   => $dayNames,
            'startKg'    => $startKg,
            'currentKg'  => $currentKg,
        ]);
    }
}
