<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ClientInvitation;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envoi de l'email d'invitation client.
 *
 * L'envoi est best-effort : si le transport échoue (ex. MAILER_DSN=null en local),
 * on log mais on ne casse pas le flux — le coach peut toujours copier le lien
 * d'acceptation affiché dans l'UI.
 */
final readonly class InvitationMailer
{
    private const FROM_EMAIL = 'no-reply@coachingpro.ubikd.com';
    private const FROM_NAME  = 'CoachPro';

    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /** Lien public d'acceptation (absolu, pour email et affichage). */
    public function acceptUrl(ClientInvitation $invitation): string
    {
        return $this->urlGenerator->generate(
            'app_invitation_accept',
            ['token' => $invitation->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /** Tente l'envoi. Retourne true si parti sans exception, false sinon. */
    public function send(ClientInvitation $invitation): bool
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($invitation->getEmail())
            ->subject(sprintf('%s t\'invite à rejoindre CoachPro', $invitation->getCoach()->getFirstName()))
            ->htmlTemplate('emails/invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'acceptUrl'  => $this->acceptUrl($invitation),
            ]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Envoi email invitation échoué : {error}', [
                'error'        => $e->getMessage(),
                'invitationId' => (string) $invitation->getId(),
            ]);
            return false;
        }
    }
}
