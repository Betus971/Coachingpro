<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ExerciseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catalogue d'exercices. Référentiel partagé.
 *
 * - Les exercices "system" (createdBy = null) sont visibles par tous (squat, bench, etc.).
 * - Un coach peut créer ses propres variations privées (createdBy = coach).
 *   Visibles uniquement par lui et ses clients via filtre dédié.
 */
#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
#[ORM\Index(columns: ['muscle_group'], name: 'idx_exercise_muscle')]
#[ORM\Index(columns: ['external_id'], name: 'idx_exercise_external')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('ROLE_COACH')", processor: 'App\State\CurrentUserOwnershipProcessor'),
        new Patch(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['exercise:read']],
    denormalizationContext: ['groups' => ['exercise:write']],
)]
class Exercise
{
    public const MUSCLE_CHEST      = 'chest';
    public const MUSCLE_BACK       = 'back';
    public const MUSCLE_SHOULDERS  = 'shoulders';
    public const MUSCLE_BICEPS     = 'biceps';
    public const MUSCLE_TRICEPS    = 'triceps';
    public const MUSCLE_QUADS      = 'quads';
    public const MUSCLE_HAMSTRINGS = 'hamstrings';
    public const MUSCLE_GLUTES     = 'glutes';
    public const MUSCLE_CALVES     = 'calves';
    public const MUSCLE_CORE       = 'core';
    public const MUSCLE_CARDIO     = 'cardio';
    /** @deprecated Use specific groups above */
    public const MUSCLE_LEGS  = 'legs';
    public const MUSCLE_ARMS  = 'arms';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['exercise:read'])]
    private Uuid $id;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Groups(['exercise:read', 'exercise:write'])]
    private string $name;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(callback: 'getMuscleGroups')]
    #[Groups(['exercise:read', 'exercise:write'])]
    private string $muscleGroup;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['exercise:read', 'exercise:write'])]
    private ?string $equipment = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['exercise:read', 'exercise:write'])]
    private ?string $description = null;

    /** URL de l'illustration (catalogue Free Exercise DB, domaine public, via CDN). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['exercise:read', 'exercise:write'])]
    private ?string $imageUrl = null;

    /** Identifiant source (ex: Free Exercise DB) pour un import idempotent. */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['exercise:read'])]
    private ?string $externalId = null;

    /**
     * Null = exercice système (visible par tous). Sinon = privé au coach et ses clients.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['exercise:read'])]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    /** @return list<string> */
    public static function getMuscleGroups(): array
    {
        return [
            self::MUSCLE_CHEST, self::MUSCLE_BACK, self::MUSCLE_SHOULDERS,
            self::MUSCLE_BICEPS, self::MUSCLE_TRICEPS,
            self::MUSCLE_QUADS, self::MUSCLE_HAMSTRINGS, self::MUSCLE_GLUTES, self::MUSCLE_CALVES,
            self::MUSCLE_CORE, self::MUSCLE_CARDIO,
            self::MUSCLE_LEGS, self::MUSCLE_ARMS, // rétrocompat
        ];
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }
    public function getMuscleGroup(): string { return $this->muscleGroup; }
    public function setMuscleGroup(string $m): self { $this->muscleGroup = $m; return $this; }
    public function getEquipment(): ?string { return $this->equipment; }
    public function setEquipment(?string $e): self { $this->equipment = $e; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $u): self { $this->imageUrl = $u; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $e): self { $this->externalId = $e; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $u): self { $this->createdBy = $u; return $this; }
}
