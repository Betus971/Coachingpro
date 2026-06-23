<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MealPhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Une photo de repas rattachée au bilan nutritionnel d'un jour (NutritionLog).
 * Plusieurs photos par jour = plusieurs repas.
 */
#[ORM\Entity(repositoryClass: MealPhotoRepository::class)]
#[ORM\Table(name: 'meal_photo')]
#[ORM\Index(columns: ['nutrition_log_id'], name: 'idx_meal_photo_log')]
class MealPhoto
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: NutritionLog::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private NutritionLog $nutritionLog;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id        = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getNutritionLog(): NutritionLog { return $this->nutritionLog; }
    public function setNutritionLog(NutritionLog $l): self { $this->nutritionLog = $l; return $this; }
    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $f): self { $this->filename = $f; return $this; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $c): self { $this->caption = $c; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
