<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Challenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Challenge>
 */
class ChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Challenge::class);
    }

    /** @return Challenge[] Défis personnalisés créés par cet utilisateur. */
    public function findCustomForUser(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.createdBy = :user')
            ->andWhere('c.isPreset = false')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
