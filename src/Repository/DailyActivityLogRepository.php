<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DailyActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyActivityLog>
 */
class DailyActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyActivityLog::class);
    }

    // Tu pourras ajouter tes requêtes custom ici plus tard
    // (ex: récupérer la somme des pas sur une semaine pour le dashboard)
}
