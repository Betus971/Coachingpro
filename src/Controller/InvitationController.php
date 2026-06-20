<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ClientInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Acceptation publique d'une invitation client (lien par email).
 * Le client définit son mot de passe -> création du compte ROLE_CLIENT
 * rattaché au coach. Route publique (voir access_control ^/invitation).
 */
class InvitationController extends AbstractController
{
    #[Route('/invitation/{token}', name: 'app_invitation_accept', methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        Request $request,
        ClientInvitationRepository $invitations,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $invitation = $invitations->findOneByToken($token);

        // États non exploitables -> page d'info sans formulaire.
        $state = match (true) {
            $invitation === null        => 'invalid',
            !$invitation->isPending()   => 'used',
            $invitation->isExpired()    => 'expired',
            default                     => 'ok',
        };

        $error = null;

        if ($state === 'ok' && $request->isMethod('POST')) {
            $firstName = trim((string) $request->request->get('first_name', '')) ?: $invitation->getFirstName();
            $lastName  = trim((string) $request->request->get('last_name', '')) ?: $invitation->getLastName();
            $password  = (string) $request->request->get('password', '');
            $confirm   = (string) $request->request->get('confirm', '');

            if (!$firstName || !$lastName) {
                $error = 'Prénom et nom sont obligatoires.';
            } elseif (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== $confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $invitation->getEmail()])) {
                $error = 'Un compte existe déjà avec cet email. Connecte-toi directement.';
            } else {
                $client = (new User())
                    ->setEmail($invitation->getEmail())
                    ->setFirstName($firstName)
                    ->setLastName($lastName)
                    ->setRoles([User::ROLE_CLIENT])
                    ->setCoach($invitation->getCoach());
                $client->setPassword($hasher->hashPassword($client, $password));

                $em->persist($client);
                $invitation->accept($client);
                $em->flush();

                $this->addFlash('success', 'Compte créé ! Connecte-toi pour accéder à ton espace.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('invitation/accept.html.twig', [
            'invitation' => $invitation,
            'state'      => $state,
            'error'      => $error,
        ]);
    }
}
