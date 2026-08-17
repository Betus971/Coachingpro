<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WorkoutSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<WorkoutSet> */
class WorkoutSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutSet::class);
    }

    /**
     * @return array<int, array{exercise_id: string, name: string, max_weight: float, date: \DateTimeImmutable}>
     */
    public function getPersonalRecords(\App\Entity\User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        // On récupère le max weight par exercice
        $sql = '
            SELECT 
                e.id as exercise_id, 
                e.name, 
                MAX(ws.weight_kg) as max_weight
            FROM workout_set ws
            JOIN workout_session s ON ws.session_id = s.id
            JOIN exercise e ON ws.exercise_id = e.id
            WHERE s.user_id = :user_id 
              AND ws.weight_kg IS NOT NULL
              AND ws.is_warmup = false
            GROUP BY e.id, e.name
            ORDER BY max_weight DESC
            LIMIT 10
        ';
        
        $resultSet = $conn->executeQuery($sql, ['user_id' => $user->getId()->toRfc4122()]);
        return $resultSet->fetchAllAssociative();
    }

    /**
     * @return array<int, array{date: string, total_volume: float}>
     */
    public function getVolumeHistory(\App\Entity\User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                DATE(s.performed_at) as date, 
                SUM(ws.reps * COALESCE(ws.weight_kg, 0)) as total_volume
            FROM workout_set ws
            JOIN workout_session s ON ws.session_id = s.id
            WHERE s.user_id = :user_id
              AND ws.is_warmup = false
            GROUP BY DATE(s.performed_at)
            ORDER BY date ASC
            LIMIT 30
        ';
        
        $resultSet = $conn->executeQuery($sql, ['user_id' => $user->getId()->toRfc4122()]);
        return $resultSet->fetchAllAssociative();
    }

    /**
     * Liste des exercices que l'utilisateur a réellement travaillés (pour le sélecteur).
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getTrainedExercises(\App\Entity\User $user): array
    {
        $sql = '
            SELECT DISTINCT e.id AS id, e.name AS name
            FROM workout_set ws
            JOIN workout_session s ON ws.session_id = s.id
            JOIN exercise e ON ws.exercise_id = e.id
            WHERE s.user_id = :user_id AND ws.is_warmup = false
            ORDER BY e.name ASC
        ';

        return $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['user_id' => $user->getId()->toRfc4122()])
            ->fetchAllAssociative();
    }

    /**
     * Volume (Reps × Poids) et charge max par séance, POUR UN exercice donné.
     *
     * @return array<int, array{date: string, total_volume: float, top_weight: ?float}>
     */
    public function getVolumeHistoryForExercise(\App\Entity\User $user, string $exerciseId): array
    {
        $sql = '
            SELECT
                DATE(s.performed_at) AS date,
                SUM(ws.reps * COALESCE(ws.weight_kg, 0)) AS total_volume,
                MAX(ws.weight_kg) AS top_weight
            FROM workout_set ws
            JOIN workout_session s ON ws.session_id = s.id
            WHERE s.user_id = :user_id
              AND ws.exercise_id = :exercise_id
              AND ws.is_warmup = false
            GROUP BY DATE(s.performed_at)
            ORDER BY date ASC
            LIMIT 30
        ';

        return $this->getEntityManager()->getConnection()
            ->executeQuery($sql, [
                'user_id'     => $user->getId()->toRfc4122(),
                'exercise_id' => $exerciseId,
            ])
            ->fetchAllAssociative();
    }
}
