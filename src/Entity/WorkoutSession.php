<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\WorkoutSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Séance RÉELLEMENT effectuée par un user (≠ template).
 * Peut référencer le WorkoutTemplate dont elle dérive (pour comparer prescrit vs réalisé)
 * mais c'est optionnel — un user peut logger une séance "freestyle".
 */
#[ORM\Entity(repositoryClass: WorkoutSessionRepository::class)]
#[ORM\Index(columns: ['user_id', 'performed_at'], name: 'idx_session_user_date')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(securityPostDenormalize: "is_granted('CREATE', object)"),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['session:read']],
    denormalizationContext: ['groups' => ['session:write']],
    order: ['performedAt' => 'DESC'],
)]
class WorkoutSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['session:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['session:read', 'session:write'])]
    private User $user;

    /** Référence vers le template-source (nullable, pour les séances freestyle). */
    #[ORM\ManyToOne(targetEntity: WorkoutTemplate::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['session:read', 'session:write'])]
    private ?WorkoutTemplate $sourceTemplate = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Groups(['session:read', 'session:write'])]
    private string $name;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Groups(['session:read', 'session:write'])]
    private \DateTimeImmutable $performedAt;

    /** Durée totale en minutes. */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 600)]
    #[Groups(['session:read', 'session:write'])]
    private ?int $durationMinutes = null;

    /** RPE global de la séance (1-10) — ressenti d'effort. */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 1, max: 10)]
    #[Groups(['session:read', 'session:write'])]
    private ?int $rpe = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?string $notes = null;

    /** @var Collection<int, WorkoutSet> */
    #[ORM\OneToMany(mappedBy: 'session', targetEntity: WorkoutSet::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['exercisePosition' => 'ASC', 'setNumber' => 'ASC'])]
    #[Groups(['session:read', 'session:write'])]
    private Collection $sets;

    #[ORM\Column]
    #[Groups(['session:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->sets = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getSourceTemplate(): ?WorkoutTemplate { return $this->sourceTemplate; }
    public function setSourceTemplate(?WorkoutTemplate $t): self { $this->sourceTemplate = $t; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }
    public function getPerformedAt(): \DateTimeImmutable { return $this->performedAt; }
    public function setPerformedAt(\DateTimeImmutable $d): self { $this->performedAt = $d; return $this; }
    public function getDurationMinutes(): ?int { return $this->durationMinutes; }
    public function setDurationMinutes(?int $d): self { $this->durationMinutes = $d; return $this; }
    public function getRpe(): ?int { return $this->rpe; }
    public function setRpe(?int $r): self { $this->rpe = $r; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
    /** @return Collection<int, WorkoutSet> */
    public function getSets(): Collection { return $this->sets; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
