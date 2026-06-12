<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoalAdjustment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoalAdjustment>
 */
class GoalAdjustmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoalAdjustment::class);
    }
}
