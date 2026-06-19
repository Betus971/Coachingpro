<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\FavoriteExercise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteExercise>
 */
class FavoriteExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteExercise::class);
    }

    /** @return Exercise[] Les exercices favoris d'un utilisateur (ordre alpha). */
    public function findExercisesForUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->select('e')
            ->join('f.exercise', 'e')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndExercise(User $user, Exercise $exercise): ?FavoriteExercise
    {
        return $this->findOneBy(['user' => $user, 'exercise' => $exercise]);
    }
}
