<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\AdjustmentTrigger;
use App\Repository\GoalAdjustmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Trace append-only d'une modulation d'objectif. Immutable côté API
 * (lecture seule : aucune écriture directe, c'est le moteur qui crée).
 *
 * Permet à un coach SaaS de justifier "pourquoi l'IA a repoussé l'échéance
 * de 3 semaines le 12/06" : on garde l'avant/après + le motif + la projection.
 */
#[ORM\Entity(repositoryClass: GoalAdjustmentRepository::class)]
#[ORM\Index(columns: ['goal_id', 'created_at'], name: 'idx_adjustment_goal_date')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
    ],
    normalizationContext: ['groups' => ['adjustment:read']],
)]
class GoalAdjustment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Goal::class, inversedBy: 'adjustments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['adjustment:read'])]
    private Goal $goal;

    #[ORM\Column(enumType: AdjustmentTrigger::class)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private AdjustmentTrigger $trigger;

    /** Dimension modifiée : 'rate' | 'deadline' | 'target' (= Goal.mode). */
    #[ORM\Column(length: 20)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private string $dimension;

    /** Valeur avant / après, sérialisées (date ISO ou nombre selon la dimension). */
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private ?string $previousValue = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private ?string $newValue = null;

    /** Valeur projetée par la régression au moment de la décision (ex: ETA réelle). */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private ?string $projectedValue = null;

    /** Écart vs trajectoire idéale, en % (négatif = en retard). */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private ?string $deviationPercent = null;

    /** Explication lisible (texte IA ou message système). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['adjustment:read', 'goal:read'])]
    private ?string $reason = null;

    #[ORM\Column]
    #[Groups(['adjustment:read', 'goal:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id        = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getGoal(): Goal { return $this->goal; }
    public function setGoal(Goal $g): self { $this->goal = $g; return $this; }
    public function getTrigger(): AdjustmentTrigger { return $this->trigger; }
    public function setTrigger(AdjustmentTrigger $t): self { $this->trigger = $t; return $this; }
    public function getDimension(): string { return $this->dimension; }
    public function setDimension(string $d): self { $this->dimension = $d; return $this; }
    public function getPreviousValue(): ?string { return $this->previousValue; }
    public function setPreviousValue(?string $v): self { $this->previousValue = $v; return $this; }
    public function getNewValue(): ?string { return $this->newValue; }
    public function setNewValue(?string $v): self { $this->newValue = $v; return $this; }
    public function getProjectedValue(): ?string { return $this->projectedValue; }
    public function setProjectedValue(?string $v): self { $this->projectedValue = $v; return $this; }
    public function getDeviationPercent(): ?string { return $this->deviationPercent; }
    public function setDeviationPercent(?string $v): self { $this->deviationPercent = $v; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $r): self { $this->reason = $r; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
