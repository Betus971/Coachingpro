<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Programme d'entraînement (template). Créé par un coach (ou un client en self-service).
 * Réutilisable: peut être assigné à plusieurs clients via ProgramAssignment.
 *
 * Exemple "PPL 4x/sem 121→95kg" = 1 Program avec 4 WorkoutTemplate (Push/Pull/Legs/Cardio).
 */
#[ORM\Entity(repositoryClass: ProgramRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['program:read']],
    denormalizationContext: ['groups' => ['program:write']],
)]
class Program
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['program:read'])]
    private Uuid $id;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Groups(['program:read', 'program:write'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['program:read', 'program:write'])]
    private ?string $description = null;

    /**
     * Auteur du programme. Pour la sécurité: un coach voit les programmes qu'il a créés
     * + ceux qui lui sont éventuellement partagés. Le `user` cible (qui fait le programme)
     * est porté par ProgramAssignment, pas ici — c'est ce qui rend le template réutilisable.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['program:read'])]
    private User $createdBy;

    /**
     * Durée en semaines. Optionnel (un programme "permanent" peut être null).
     */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 1, max: 104)]
    #[Groups(['program:read', 'program:write'])]
    private ?int $durationWeeks = null;

    /** @var Collection<int, WorkoutTemplate> */
    #[ORM\OneToMany(mappedBy: 'program', targetEntity: WorkoutTemplate::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dayOfWeek' => 'ASC'])]
    #[Groups(['program:read', 'program:write'])]
    private Collection $workoutTemplates;

    /** @var Collection<int, ProgramAssignment> */
    #[ORM\OneToMany(mappedBy: 'program', targetEntity: ProgramAssignment::class)]
    private Collection $assignments;

    #[ORM\Column]
    #[Groups(['program:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->workoutTemplates = new ArrayCollection();
        $this->assignments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $u): self { $this->createdBy = $u; return $this; }
    public function getDurationWeeks(): ?int { return $this->durationWeeks; }
    public function setDurationWeeks(?int $d): self { $this->durationWeeks = $d; return $this; }
    /** @return Collection<int, WorkoutTemplate> */
    public function getWorkoutTemplates(): Collection { return $this->workoutTemplates; }
    /** @return Collection<int, ProgramAssignment> */
    public function getAssignments(): Collection { return $this->assignments; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
