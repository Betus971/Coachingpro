<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChallengeParticipationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChallengeParticipationRepository::class)]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_challenge_participation_user_status')]
class ChallengeParticipation
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Challenge::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Challenge $challenge;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(length: 15)]
    private string $status = self::STATUS_ACTIVE;

    /** @var Collection<int, ChallengeCheckIn> */
    #[ORM\OneToMany(mappedBy: 'participation', targetEntity: ChallengeCheckIn::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $checkIns;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id        = Uuid::v7();
        $this->startedAt = new \DateTimeImmutable('today');
        $this->createdAt = new \DateTimeImmutable();
        $this->checkIns  = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getChallenge(): Challenge { return $this->challenge; }
    public function setChallenge(Challenge $c): self { $this->challenge = $c; return $this; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(\DateTimeImmutable $d): self { $this->startedAt = $d; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, ChallengeCheckIn> */
    public function getCheckIns(): Collection { return $this->checkIns; }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }

    /** @return int[] */
    public function getCheckedDays(): array
    {
        return $this->checkIns->map(fn(ChallengeCheckIn $ci) => $ci->getDayNumber())->toArray();
    }

    /** Number of elapsed days since start (1-indexed, capped at durationDays). */
    public function getDaysElapsed(): int
    {
        $diff = $this->startedAt->diff(new \DateTimeImmutable('today'));
        return min($diff->days + 1, $this->challenge->getDurationDays());
    }

    public function getProgressPercent(): int
    {
        $total = $this->challenge->getDurationDays();
        if ($total === 0) return 0;
        return (int) round(count($this->checkIns) / $total * 100);
    }

    /** Count of consecutive checked days going back from today. */
    public function getCurrentStreak(): int
    {
        $days  = $this->getCheckedDays();
        if (empty($days)) return 0;
        $daysSet = array_flip($days);
        $streak  = 0;
        for ($d = $this->getDaysElapsed(); $d >= 1; $d--) {
            if (isset($daysSet[$d])) {
                $streak++;
            } else {
                break;
            }
        }
        return $streak;
    }

    public function getDaysRemaining(): int
    {
        $duration = $this->challenge->getDurationDays();
        return max(0, $duration - $this->getDaysElapsed() + 1);
    }
}
