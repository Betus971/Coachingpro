<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Challenge;
use App\Entity\ChallengeParticipation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChallengeParticipation>
 */
class ChallengeParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChallengeParticipation::class);
    }

    /** @return ChallengeParticipation[] */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('cp')
            ->innerJoin('cp.challenge', 'c')
            ->addSelect('c')
            ->leftJoin('cp.checkIns', 'ci')
            ->addSelect('ci')
            ->where('cp.user = :user')
            ->andWhere('cp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ChallengeParticipation::STATUS_ACTIVE)
            ->orderBy('cp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForUserAndChallenge(User $user, Challenge $challenge): ?ChallengeParticipation
    {
        return $this->createQueryBuilder('cp')
            ->where('cp.user = :user')
            ->andWhere('cp.challenge = :challenge')
            ->andWhere('cp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('challenge', $challenge)
            ->setParameter('status', ChallengeParticipation::STATUS_ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ChallengeParticipation[] */
    public function findHistoryForUser(User $user): array
    {
        return $this->createQueryBuilder('cp')
            ->innerJoin('cp.challenge', 'c')
            ->addSelect('c')
            ->where('cp.user = :user')
            ->andWhere('cp.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                ChallengeParticipation::STATUS_COMPLETED,
                ChallengeParticipation::STATUS_ABANDONED,
            ])
            ->orderBy('cp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
