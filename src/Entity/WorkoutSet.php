<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkoutSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une série effectuée pendant une WorkoutSession.
 *
 * On dénormalise volontairement `exercise` ici (au lieu de tout passer par ExerciseTemplate)
 * pour permettre des séries en freestyle (pas de template) et pour figer l'exercice fait
 * même si le template change ensuite (audit-friendly).
 *
 * `exercisePosition` est un int qui reflète l'ordre dans la séance — c'est ce qui permet de
 * grouper les sets par exercice côté front sans relation explicite "WorkoutExercise".
 */
#[ORM\Entity(repositoryClass: WorkoutSetRepository::class)]
class WorkoutSet
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['session:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: WorkoutSession::class, inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkoutSession $session;

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read', 'session:write'])]
    private Exercise $exercise;

    /** Position de l'exercice dans la séance (groupe les sets entre eux côté front). */
    #[ORM\Column(type: 'smallint')]
    #[Groups(['session:read', 'session:write'])]
    private int $exercisePosition = 0;

    /** N° de série (1, 2, 3...). */
    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 50)]
    #[Groups(['session:read', 'session:write'])]
    private int $setNumber;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 1000)]
    #[Groups(['session:read', 'session:write'])]
    private int $reps;

    /** Charge soulevée (kg). Decimal pour les disques de 1.25kg. */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?string $weightKg = null;

    /** RPE de la série (1-10). */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 1, max: 10)]
    #[Groups(['session:read', 'session:write'])]
    private ?int $rpe = null;

    /** Pour cardio: distance en mètres et durée en secondes. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?int $distanceM = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?int $durationSeconds = null;

    #[ORM\Column]
    #[Groups(['session:read', 'session:write'])]
    private bool $isWarmup = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid { return $this->id; }
    public function getSession(): WorkoutSession { return $this->session; }
    public function setSession(WorkoutSession $s): self { $this->session = $s; return $this; }
    public function getExercise(): Exercise { return $this->exercise; }
    public function setExercise(Exercise $e): self { $this->exercise = $e; return $this; }
    public function getExercisePosition(): int { return $this->exercisePosition; }
    public function setExercisePosition(int $p): self { $this->exercisePosition = $p; return $this; }
    public function getSetNumber(): int { return $this->setNumber; }
    public function setSetNumber(int $n): self { $this->setNumber = $n; return $this; }
    public function getReps(): int { return $this->reps; }
    public function setReps(int $r): self { $this->reps = $r; return $this; }
    public function getWeightKg(): ?string { return $this->weightKg; }
    public function setWeightKg(?string $w): self { $this->weightKg = $w; return $this; }
    public function getRpe(): ?int { return $this->rpe; }
    public function setRpe(?int $r): self { $this->rpe = $r; return $this; }
    public function getDistanceM(): ?int { return $this->distanceM; }
    public function setDistanceM(?int $d): self { $this->distanceM = $d; return $this; }
    public function getDurationSeconds(): ?int { return $this->durationSeconds; }
    public function setDurationSeconds(?int $d): self { $this->durationSeconds = $d; return $this; }
    public function isWarmup(): bool { return $this->isWarmup; }
    public function setIsWarmup(bool $w): self { $this->isWarmup = $w; return $this; }
}
