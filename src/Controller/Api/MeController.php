<?php

declare(strict_types=1);

namespace App\Controller\Api;

use ApiPlatform\Metadata\Get;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Endpoint commodité pour récupérer l'utilisateur courant via JWT.
 * Évite au front de stocker l'ID après login : il appelle /api/me et reçoit son profil.
 *
 * Sérialisé avec les groupes 'user:read' (mêmes que l'ApiResource User).
 */
#[Route('/api/me', name: 'api_me', methods: ['GET'])]
final class MeController extends AbstractController
{
    public function __invoke(SerializerInterface $serializer): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $json = $serializer->serialize($user, 'json', ['groups' => ['user:read']]);

        return new JsonResponse($json, 200, [], true);
    }
}
