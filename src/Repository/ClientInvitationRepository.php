<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ClientInvitation;
use App\Entity\User;
use App\Enum\InvitationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientInvitation>
 */
class ClientInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientInvitation::class);
    }

    public function findOneByToken(string $token): ?ClientInvitation
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Invitations encore en attente d'un coach (les plus récentes d'abord).
     *
     * @return ClientInvitation[]
     */
    public function findPendingForCoach(User $coach): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.coach = :coach')
            ->andWhere('i.status = :status')
            ->setParameter('coach', $coach->getId(), 'uuid')
            ->setParameter('status', InvitationStatus::Pending)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
