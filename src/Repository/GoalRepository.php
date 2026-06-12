<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goal;
use App\Entity\User;
use App\Enum\GoalStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Goal>
 */
class GoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goal::class);
    }

    /** @return Goal[] Objectifs ouverts d'un utilisateur, à évaluer par le moteur. */
    public function findOpenForUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status IN (:open)')
            ->setParameter('user', $user)
            ->setParameter('open', [GoalStatus::Active, GoalStatus::Recalibrated])
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
