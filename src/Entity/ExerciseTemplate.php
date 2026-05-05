<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExerciseTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prescription d'un exercice dans une séance type.
 * Ex: "DC barre — 4 séries de 8-10 reps à 70kg, repos 90s".
 *
 * On garde ranges (min/max) pour la flexibilité — un coach prescrit rarement "exactement 10 reps".
 */
#[ORM\Entity(repositoryClass: ExerciseTemplateRepository::class)]
class ExerciseTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['program:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: WorkoutTemplate::class, inversedBy: 'exerciseTemplates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkoutTemplate $workoutTemplate;

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['program:read', 'program:write'])]
    private Exercise $exercise;

    /** Ordre dans la séance (0, 1, 2...). */
    #[ORM\Column(type: 'smallint')]
    #[Groups(['program:read', 'program:write'])]
    private int $position = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 20)]
    #[Groups(['program:read', 'program:write'])]
    private int $targetSets;

    #[ORM\Column(type: 'smallint')]
    #[Groups(['program:read', 'program:write'])]
    private int $targetRepsMin;

    #[ORM\Column(type: 'smallint')]
    #[Groups(['program:read', 'program:write'])]
    private int $targetRepsMax;

    /** Charge cible (kg). Optionnel — souvent prescrit en %1RM ou RPE. */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    #[Groups(['program:read', 'program:write'])]
    private ?string $targetWeightKg = null;

    /** Repos en secondes. */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Groups(['program:read', 'program:write'])]
    private ?int $restSeconds = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['program:read', 'program:write'])]
    private ?string $notes = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid { return $this->id; }
    public function getWorkoutTemplate(): WorkoutTemplate { return $this->workoutTemplate; }
    public function setWorkoutTemplate(WorkoutTemplate $w): self { $this->workoutTemplate = $w; return $this; }
    public function getExercise(): Exercise { return $this->exercise; }
    public function setExercise(Exercise $e): self { $this->exercise = $e; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): self { $this->position = $p; return $this; }
    public function getTargetSets(): int { return $this->targetSets; }
    public function setTargetSets(int $s): self { $this->targetSets = $s; return $this; }
    public function getTargetRepsMin(): int { return $this->targetRepsMin; }
    public function setTargetRepsMin(int $r): self { $this->targetRepsMin = $r; return $this; }
    public function getTargetRepsMax(): int { return $this->targetRepsMax; }
    public function setTargetRepsMax(int $r): self { $this->targetRepsMax = $r; return $this; }
    public function getTargetWeightKg(): ?string { return $this->targetWeightKg; }
    public function setTargetWeightKg(?string $w): self { $this->targetWeightKg = $w; return $this; }
    public function getRestSeconds(): ?int { return $this->restSeconds; }
    public function setRestSeconds(?int $r): self { $this->restSeconds = $r; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
}
