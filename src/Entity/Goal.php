<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\GoalMode;
use App\Enum\GoalStatus;
use App\Enum\GoalType;
use App\Repository\GoalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Objectif modulable de l'utilisateur (ex: 121 -> 95 kg avant le 01/12).
 *
 * Première classe = ce qui permet à l'IA de recalibrer cible / délai / rythme
 * de façon persistante et auditée, là où c'était auparavant codé en dur dans
 * les prompts de MistralCoachService.
 *
 * Le couple (startValue, targetValue, targetDate) + le `mode` définit ce que
 * le moteur d'ajustement a le droit de bouger. Chaque modulation crée un
 * GoalAdjustment (historique append-only).
 *
 * Sécurité : mêmes Voters VIEW/EDIT que Program/WeightLog — un client ne voit
 * que ses objectifs, un coach ceux de ses clients.
 */
#[ORM\Entity(repositoryClass: GoalRepository::class)]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_goal_user_status')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('ROLE_USER')", processor: 'App\State\CurrentUserOwnershipProcessor'),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['goal:read']],
    denormalizationContext: ['groups' => ['goal:write']],
    order: ['createdAt' => 'DESC'],
)]
class Goal
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['goal:read'])]
    private Uuid $id;

    /** Bénéficiaire de l'objectif (le client). Pas forcément le créateur. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['goal:read'])]
    private User $user;

    #[ORM\Column(enumType: GoalType::class)]
    #[Assert\NotNull]
    #[Groups(['goal:read', 'goal:write'])]
    private GoalType $type;

    #[ORM\Column(enumType: GoalMode::class)]
    #[Assert\NotNull]
    #[Groups(['goal:read', 'goal:write'])]
    private GoalMode $mode = GoalMode::FixedRate;

    #[ORM\Column(enumType: GoalStatus::class)]
    #[Groups(['goal:read', 'goal:write'])]
    private GoalStatus $status = GoalStatus::Active;

    /** Valeur de départ (ex: 121.20 kg) — figée à la création. */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)]
    #[Assert\NotNull]
    #[Groups(['goal:read', 'goal:write'])]
    private string $startValue;

    /** Cible (ex: 95.00 kg). Modifiable seulement si mode = FixedTarget. */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)]
    #[Assert\NotNull]
    #[Groups(['goal:read', 'goal:write'])]
    private string $targetValue;

    /**
     * Rythme cible en unité/semaine (ex: 0.70 kg/sem). Sert de garde-fou santé.
     * Ajustable si mode = FixedDeadline. Nullable : peut être dérivé.
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Positive]
    #[Groups(['goal:read', 'goal:write'])]
    private ?string $weeklyRate = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['goal:read', 'goal:write'])]
    private \DateTimeImmutable $startDate;

    /** Échéance. Ajustable si mode = FixedRate. */
    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'startDate')]
    #[Groups(['goal:read', 'goal:write'])]
    private \DateTimeImmutable $targetDate;

    /** Programme rattaché (facultatif) que l'IA pourra retoucher. */
    #[ORM\ManyToOne(targetEntity: Program::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['goal:read', 'goal:write'])]
    private ?Program $program = null;

    /** @var Collection<int, GoalAdjustment> Historique des modulations. */
    #[ORM\OneToMany(mappedBy: 'goal', targetEntity: GoalAdjustment::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['goal:read'])]
    private Collection $adjustments;

    #[ORM\Column]
    #[Groups(['goal:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['goal:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id          = Uuid::v7();
        $this->adjustments = new ArrayCollection();
        $this->startDate   = new \DateTimeImmutable('today');
        $this->createdAt   = new \DateTimeImmutable();
        $this->updatedAt   = new \DateTimeImmutable();
    }

    /** Distance totale à parcourir (toujours positive). */
    public function totalDelta(): float
    {
        return abs((float) $this->targetValue - (float) $this->startValue);
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getType(): GoalType { return $this->type; }
    public function setType(GoalType $t): self { $this->type = $t; return $this; }
    public function getMode(): GoalMode { return $this->mode; }
    public function setMode(GoalMode $m): self { $this->mode = $m; return $this; }
    public function getStatus(): GoalStatus { return $this->status; }
    public function setStatus(GoalStatus $s): self { $this->status = $s; return $this; }
    public function getStartValue(): string { return $this->startValue; }
    public function setStartValue(string $v): self { $this->startValue = $v; return $this; }
    public function getTargetValue(): string { return $this->targetValue; }
    public function setTargetValue(string $v): self { $this->targetValue = $v; return $this; }
    public function getWeeklyRate(): ?string { return $this->weeklyRate; }
    public function setWeeklyRate(?string $r): self { $this->weeklyRate = $r; return $this; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function setStartDate(\DateTimeImmutable $d): self { $this->startDate = $d; return $this; }
    public function getTargetDate(): \DateTimeImmutable { return $this->targetDate; }
    public function setTargetDate(\DateTimeImmutable $d): self { $this->targetDate = $d; return $this; }
    public function getProgram(): ?Program { return $this->program; }
    public function setProgram(?Program $p): self { $this->program = $p; return $this; }
    /** @return Collection<int, GoalAdjustment> */
    public function getAdjustments(): Collection { return $this->adjustments; }

    public function addAdjustment(GoalAdjustment $a): self
    {
        if (!$this->adjustments->contains($a)) {
            $this->adjustments->add($a);
            $a->setGoal($this);
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
