<?php

declare(strict_types=1);

namespace App\Controller;

use App\Coach\CoachActionExecutor;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Enum\CoachActionType;
use App\Repository\ChatMessageRepository;
use App\Service\MistralCoachService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat', name: 'app_chat_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
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
        MistralCoachService $coach,
        CoachActionExecutor $actionExecutor,
        EntityManagerInterface $em,
        RateLimiterFactory $chatSendLimiter,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $limiter = $chatSendLimiter->create($user->getId()->toRfc4122());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Trop de messages, attends une minute.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }
        $message = trim($data['message'] ?? '');

        if ($message === '') {
            return $this->json(['error' => 'Message vide'], Response::HTTP_BAD_REQUEST);
        }

        $session = $request->getSession();
        $pending = $session->get('pending_coach_action');

        // Une action est en attente : ce message est une confirmation (oui / annule).
        if (is_array($pending)) {
            $reply = $this->resolvePendingAction($user, $message, $pending, $actionExecutor, $session);
            if ($reply !== null) {
                return $this->persistAndRespond($user, $message, $reply, $repo, $em);
            }
            // Ni oui ni non clair : on abandonne l'attente et on traite normalement.
            $session->remove('pending_coach_action');
        }

        $history = array_map(
            fn (ChatMessage $m) => ['role' => $m->getRole(), 'content' => $m->getContent()],
            $repo->findLastN($user, 20)
        );

        // Appel IA : peut renvoyer une action proposée (à confirmer) via $action.
        $action     = null;
        $aiResponse = $coach->chat($user, $message, $history, $action);
        if (is_array($action)) {
            $session->set('pending_coach_action', $action);
        }

        return $this->persistAndRespond($user, $message, $aiResponse, $repo, $em);
    }

    /**
     * Interprète une réponse de confirmation. Retourne le message à afficher,
     * ou null si la réponse n'est ni un oui ni un non clair.
     *
     * @param array{type?: string, payload?: array} $pending
     */
    private function resolvePendingAction(
        User $user,
        string $message,
        array $pending,
        CoachActionExecutor $exec,
        SessionInterface $session,
    ): ?string {
        $m   = mb_strtolower(trim($message));
        $yes = ['oui', 'ok', 'okay', 'confirme', 'confirmer', 'valide', 'valider', 'vas-y', 'go', 'yes', 'parfait', 'd\'accord', 'daccord'];
        $no  = ['non', 'annule', 'annuler', 'stop', 'laisse', 'no'];

        $isYes = in_array($m, $yes, true) || str_starts_with($m, 'oui');
        $isNo  = in_array($m, $no, true) || str_starts_with($m, 'non') || str_starts_with($m, 'annul');
        if (!$isYes && !$isNo) {
            return null;
        }

        $session->remove('pending_coach_action');
        if ($isNo) {
            return 'Ok, j\'annule. Dis-moi si tu veux autre chose.';
        }

        $type = CoachActionType::tryFrom($pending['type'] ?? '');
        if ($type === null) {
            return 'Je n\'ai pas retrouvé l\'action à confirmer, désolé.';
        }
        try {
            return $exec->apply($user, $type, $pending['payload'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return 'Impossible d\'appliquer : ' . $e->getMessage();
        }
    }

    private function persistAndRespond(
        User $user,
        string $message,
        string $aiResponse,
        ChatMessageRepository $repo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $em->persist(new ChatMessage($user, 'user', $message));
        $modelMsg = new ChatMessage($user, 'model', $aiResponse);
        $em->persist($modelMsg);
        $em->flush();
        $repo->pruneOldMessages($user);

        return $this->json([
            'role'      => 'model',
            'content'   => $aiResponse,
            'createdAt' => $modelMsg->getCreatedAt()->format('H:i'),
        ]);
    }
}
