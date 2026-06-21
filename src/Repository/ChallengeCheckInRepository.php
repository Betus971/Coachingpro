<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChallengeCheckIn;
use App\Entity\ChallengeParticipation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChallengeCheckIn>
 */
class ChallengeCheckInRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChallengeCheckIn::class);
    }

    public function findForDay(ChallengeParticipation $participation, int $day): ?ChallengeCheckIn
    {
        return $this->findOneBy(['participation' => $participation, 'dayNumber' => $day]);
    }
}
