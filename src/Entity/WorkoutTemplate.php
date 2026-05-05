<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Repository\WorkoutTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une séance type dans un programme. Ex: "Lundi - Push", "Mercredi - Cardio HIIT".
 * Pas d'API resource standalone : on la manipule via Program (cascade).
 */
#[ORM\Entity(repositoryClass: WorkoutTemplateRepository::class)]
#[ApiResource(
    operations: [new Get(security: "is_granted('VIEW', object.getProgram())")],
    normalizationContext: ['groups' => ['program:read']],
)]
class WorkoutTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['program:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Program::class, inversedBy: 'workoutTemplates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Program $program;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['program:read', 'program:write'])]
    private string $name;

    /**
     * 1 = Lundi, 7 = Dimanche (ISO-8601). Null si la séance est "flexible" (à caler dans la semaine).
     */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 1, max: 7)]
    #[Groups(['program:read', 'program:write'])]
    private ?int $dayOfWeek = null;

    /** @var Collection<int, ExerciseTemplate> */
    #[ORM\OneToMany(mappedBy: 'workoutTemplate', targetEntity: ExerciseTemplate::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['program:read', 'program:write'])]
    private Collection $exerciseTemplates;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->exerciseTemplates = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getProgram(): Program { return $this->program; }
    public function setProgram(Program $p): self { $this->program = $p; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }
    public function getDayOfWeek(): ?int { return $this->dayOfWeek; }
    public function setDayOfWeek(?int $d): self { $this->dayOfWeek = $d; return $this; }
    /** @return Collection<int, ExerciseTemplate> */
    public function getExerciseTemplates(): Collection { return $this->exerciseTemplates; }
}
