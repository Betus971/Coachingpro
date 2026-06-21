<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChallengeCheckInRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChallengeCheckInRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_checkin_participation_day', columns: ['participation_id', 'day_number'])]
class ChallengeCheckIn
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ChallengeParticipation::class, inversedBy: 'checkIns')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChallengeParticipation $participation;

    /** 1 to durationDays */
    #[ORM\Column(type: 'smallint')]
    private int $dayNumber;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $checkedAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function __construct()
    {
        $this->id        = Uuid::v7();
        $this->checkedAt = new \DateTimeImmutable('today');
    }

    public function getId(): Uuid { return $this->id; }
    public function getParticipation(): ChallengeParticipation { return $this->participation; }
    public function setParticipation(ChallengeParticipation $p): self { $this->participation = $p; return $this; }
    public function getDayNumber(): int { return $this->dayNumber; }
    public function setDayNumber(int $d): self { $this->dayNumber = $d; return $this; }
    public function getCheckedAt(): \DateTimeImmutable { return $this->checkedAt; }
    public function setCheckedAt(\DateTimeImmutable $d): self { $this->checkedAt = $d; return $this; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $n): self { $this->note = $n; return $this; }
}
