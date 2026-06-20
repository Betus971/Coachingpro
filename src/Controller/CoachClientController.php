<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClientInvitation;
use App\Entity\User;
use App\Repository\ClientInvitationRepository;
use App\Service\InvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace coach : gestion de ses clients et des invitations.
 */
#[Route('/coach')]
#[IsGranted(User::ROLE_COACH)]
class CoachClientController extends AbstractController
{
    #[Route('/clients', name: 'app_coach_clients', methods: ['GET'])]
    public function clients(ClientInvitationRepository $invitations): Response
    {
        /** @var User $coach */
        $coach = $this->getUser();

        return $this->render('coach/clients.html.twig', [
            'clients'     => $coach->getClients(),
            'invitations' => $invitations->findPendingForCoach($coach),
        ]);
    }

    #[Route('/clients/inviter', name: 'app_coach_client_invite', methods: ['GET', 'POST'])]
    public function invite(
        Request $request,
        EntityManagerInterface $em,
        InvitationMailer $mailer,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();
        $error = null;

        if ($request->isMethod('POST')) {
            $email     = trim((string) $request->request->get('email', ''));
            $firstName = trim((string) $request->request->get('first_name', '')) ?: null;
            $lastName  = trim((string) $request->request->get('last_name', '')) ?: null;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse email invalide.';
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = 'Un compte existe déjà avec cet email.';
            } else {
                $invitation = (new ClientInvitation())
                    ->setCoach($coach)
                    ->setEmail($email)
                    ->setFirstName($firstName)
                    ->setLastName($lastName);

                $em->persist($invitation);
                $em->flush();

                $sent = $mailer->send($invitation);
                $this->addFlash(
                    'success',
                    $sent
                        ? sprintf('Invitation envoyée à %s.', $email)
                        : sprintf('Invitation créée pour %s. L\'email n\'a pas pu être envoyé (transport non configuré) — copie le lien ci-dessous.', $email),
                );

                return $this->redirectToRoute('app_coach_clients');
            }
        }

        return $this->render('coach/invite.html.twig', ['error' => $error]);
    }

    #[Route('/invitations/{id}/revoquer', name: 'app_coach_invite_revoke', methods: ['POST'])]
    public function revoke(
        ClientInvitation $invitation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();

        if (!$invitation->getCoach()->getId()->equals($coach->getId())) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('revoke' . $invitation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $invitation->revoke();
        $em->flush();
        $this->addFlash('success', 'Invitation révoquée.');

        return $this->redirectToRoute('app_coach_clients');
    }
}
