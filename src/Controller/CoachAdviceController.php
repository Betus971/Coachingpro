<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MistralCoachService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoint léger qui renvoie le conseil IA d'une page en fragment HTML.
 * Appelé en asynchrone par le widget _coach_advice.html.twig pour ne pas
 * bloquer le rendu de la page (l'appel Mistral peut être long sur cache froid).
 */
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class CoachAdviceController extends AbstractController
{
    #[Route('/coach-ia/advice/{context}', name: 'app_coach_advice', methods: ['GET'], requirements: ['context' => 'dashboard|weight|sessions'])]
    public function advice(string $context, MistralCoachService $coach): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $advice = match ($context) {
            'weight'   => $coach->getWeightAdvice($user),
            'sessions' => $coach->getSessionAdvice($user),
            default    => $coach->getDashboardAdvice($user),
        };

        // Fragment de lignes (vide si pas d'advice -> le widget se masque tout seul).
        return $this->render('_coach_advice_lines.html.twig', ['advice' => $advice]);
    }
}
