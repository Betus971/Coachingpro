<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChallengeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChallengeRepository::class)]
class Challenge
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 10)]
    private string $emoji;

    #[ORM\Column(type: 'smallint')]
    private int $durationDays = 30;

    /** nutrition | fitness | lifestyle | mindset */
    #[ORM\Column(length: 30)]
    private string $category;

    #[ORM\Column]
    private bool $isPreset = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ChallengeParticipation> */
    #[ORM\OneToMany(mappedBy: 'challenge', targetEntity: ChallengeParticipation::class)]
    private Collection $participations;

    public function __construct()
    {
        $this->id             = Uuid::v7();
        $this->createdAt      = new \DateTimeImmutable();
        $this->participations = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): self { $this->title = $t; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $d): self { $this->description = $d; return $this; }
    public function getEmoji(): string { return $this->emoji; }
    public function setEmoji(string $e): self { $this->emoji = $e; return $this; }
    public function getDurationDays(): int { return $this->durationDays; }
    public function setDurationDays(int $d): self { $this->durationDays = $d; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $c): self { $this->category = $c; return $this; }
    public function isPreset(): bool { return $this->isPreset; }
    public function setIsPreset(bool $p): self { $this->isPreset = $p; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, ChallengeParticipation> */
    public function getParticipations(): Collection { return $this->participations; }
}
