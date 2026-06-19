<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FavoriteExerciseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Exercice favori d'un utilisateur (relation user <-> exercise du catalogue partagé).
 * Permet le "pin" d'un exo pour le retrouver en tête dans le logger.
 */
#[ORM\Entity(repositoryClass: FavoriteExerciseRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_exercise', columns: ['user_id', 'exercise_id'])]
class FavoriteExercise
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Exercise $exercise;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getExercise(): Exercise { return $this->exercise; }
    public function setExercise(Exercise $e): self { $this->exercise = $e; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
