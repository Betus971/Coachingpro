<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Exercise> */
class ExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercise::class);
    }

    /**
     * Exercices les plus utilisés par l'utilisateur (par fréquence), pour
     * proposer un "ajout rapide" dans le logger.
     *
     * @return Exercise[]
     */
    public function findMostUsedForUser(User $user, int $limit = 8): array
    {
        return $this->createQueryBuilder('e')
            ->select('e')
            ->join('App\\Entity\\WorkoutSet', 'ws', 'WITH', 'ws.exercise = e')
            ->join('ws.session', 's')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->groupBy('e.id')
            ->orderBy('COUNT(ws.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
