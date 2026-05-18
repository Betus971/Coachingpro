<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * Retourne les N derniers messages d'un utilisateur, du plus ancien au plus récent.
     *
     * @return ChatMessage[]
     */
    public function findLastN(User $user, int $n = 20): array
    {
        // On récupère les N derniers en DESC, puis on inverse pour avoir l'ordre chronologique
        $results = $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($n)
            ->getQuery()
            ->getResult();

        return array_reverse($results);
    }

    /**
     * Supprime les anciens messages (garde seulement les N derniers) pour ne pas saturer la BDD.
     */
    public function pruneOldMessages(User $user, int $keep = 100): void
    {
        // Récupère les IDs à conserver
        $ids = $this->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($keep)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($ids)) {
            return;
        }

        $this->createQueryBuilder('m')
            ->delete()
            ->where('m.user = :user')
            ->andWhere('m.id NOT IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
