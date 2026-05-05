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
    public function __invoke(ProgramAssignmentRepository $assignmentRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $assignments = $assignmentRepo->findBy(['user' => $user], ['startDate' => 'DESC']);
        $active = null;
        foreach ($assignments as $a) {
            if ($a->isActive()) { $active = $a; break; }
        }

        // Jours semaine ISO
        $dayNames = [1 => 'LUNDI', 2 => 'MARDI', 3 => 'MERCREDI', 4 => 'JEUDI', 5 => 'VENDREDI', 6 => 'SAMEDI', 7 => 'DIMANCHE'];

        return $this->render('program/show.html.twig', [
            'assignment' => $active,
            'dayNames'   => $dayNames,
        ]);
    }
}
