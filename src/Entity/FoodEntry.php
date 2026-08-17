<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FoodEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un aliment saisi dans le journal nutritionnel d'une journée.
 *
 * Chaque aliment est une ligne distincte rattachée au NutritionLog du jour
 * (relation N:1). Le NutritionLog conserve les totaux du jour = somme des
 * FoodEntry qu'il contient. Ainsi on peut saisir plusieurs aliments sans
 * écraser les précédents, et supprimer un aliment individuellement.
 */
#[ORM\Entity(repositoryClass: FoodEntryRepository::class)]
#[ORM\Index(columns: ['nutrition_log_id'], name: 'idx_food_entry_log')]
class FoodEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: NutritionLog::class, inversedBy: 'foodEntries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private NutritionLog $nutritionLog;

    /** Nom libre de l'aliment (ex: "Poulet 150g", "Riz basmati"). */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $name = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    private ?int $proteinsG = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 2000)]
    private ?int $carbsG = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    private ?int $fatsG = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 10000)]
    private ?int $kcal = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 500)]
    private ?int $fiberG = null;

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
    public function getName(): ?string { return $this->name; }
    public function setName(?string $n): self { $this->name = $n; return $this; }
    public function getProteinsG(): ?int { return $this->proteinsG; }
    public function setProteinsG(?int $v): self { $this->proteinsG = $v; return $this; }
    public function getCarbsG(): ?int { return $this->carbsG; }
    public function setCarbsG(?int $v): self { $this->carbsG = $v; return $this; }
    public function getFatsG(): ?int { return $this->fatsG; }
    public function setFatsG(?int $v): self { $this->fatsG = $v; return $this; }
    public function getKcal(): ?int { return $this->kcal; }
    public function setKcal(?int $v): self { $this->kcal = $v; return $this; }
    public function getFiberG(): ?int { return $this->fiberG; }
    public function setFiberG(?int $v): self { $this->fiberG = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
