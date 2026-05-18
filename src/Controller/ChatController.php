<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatMessageRepository;
use App\Service\GeminiCoachService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat', name: 'app_chat_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ChatController extends AbstractController
{
    /**
     * Retourne les N derniers messages pour initialiser le widget.
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(ChatMessageRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $messages = $repo->findLastN($user, 30);

        return $this->json(array_map(
            fn (ChatMessage $m) => [
                'role'      => $m->getRole(),
                'content'   => $m->getContent(),
                'createdAt' => $m->getCreatedAt()->format('H:i'),
            ],
            $messages
        ));
    }

    /**
     * Reçoit un message, appelle Gemini, persiste les deux messages, retourne la réponse.
     */
    #[Route('/send', name: 'send', methods: ['POST'])]
    public function send(
        Request $request,
        ChatMessageRepository $repo,
        GeminiCoachService $gemini,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $data    = json_decode($request->getContent(), true);
        $message = trim($data['message'] ?? '');

        if ($message === '') {
            return $this->json(['error' => 'Message vide'], Response::HTTP_BAD_REQUEST);
        }

        // Récupère l'historique récent pour le contexte Gemini
        $history = array_map(
            fn (ChatMessage $m) => ['role' => $m->getRole(), 'content' => $m->getContent()],
            $repo->findLastN($user, 20)
        );

        // Appel IA
        $aiResponse = $gemini->chat($user, $message, $history);

        // Persiste le message utilisateur
        $userMsg = new ChatMessage($user, 'user', $message);
        $em->persist($userMsg);

        // Persiste la réponse du modèle
        $modelMsg = new ChatMessage($user, 'model', $aiResponse);
        $em->persist($modelMsg);

        $em->flush();

        // Nettoie les vieux messages (garde les 100 derniers)
        $repo->pruneOldMessages($user, 100);

        return $this->json([
            'role'      => 'model',
            'content'   => $aiResponse,
            'createdAt' => $modelMsg->getCreatedAt()->format('H:i'),
        ]);
    }
}
