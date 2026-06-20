<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ClientInvitation;
use App\Entity\User;
use App\Enum\InvitationStatus;
use App\Service\InvitationMailer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * POST /api/client_invitations : rattache l'invitation au coach authentifié,
 * force le statut Pending, persiste, puis envoie l'email d'invitation.
 */
final readonly class ClientInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private InvitationMailer $mailer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ClientInvitation) {
            $coach = $this->security->getUser();
            if ($coach instanceof User) {
                $data->setCoach($coach);
            }
            $data->setStatus(InvitationStatus::Pending);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($result instanceof ClientInvitation) {
            $this->mailer->send($result);
        }

        return $result;
    }
}
