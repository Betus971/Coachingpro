<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\NutritionLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Bilan macros agrégé sur la journée. Une ligne par user/jour.
 * Si tu veux track par repas plus tard, tu ajoutes une entité MealLog en relation N:1.
 *
 * Note: kcal n'est PAS recalculé côté serveur — on stocke ce que le client a saisi (un kcal indiqué
 * sur une étiquette ne suit pas exactement 4/4/9, donc cohérence avec la réalité de saisie).
 * Si tu veux du calculé en plus, ajoute kcalComputed et compare.
 */
#[ORM\Entity(repositoryClass: NutritionLogRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_nutrition_user_date', columns: ['user_id', 'logged_on'])]
#[ORM\Index(columns: ['user_id', 'logged_on'], name: 'idx_nutrition_user_date')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('ROLE_USER')", processor: 'App\State\CurrentUserOwnershipProcessor'),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['nutrition:read']],
    denormalizationContext: ['groups' => ['nutrition:write']],
    order: ['loggedOn' => 'DESC'],
)]
class NutritionLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['nutrition:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['nutrition:read'])]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private \DateTimeImmutable $loggedOn;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?int $proteinsG = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 2000)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?int $carbsG = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?int $fatsG = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 10000)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?int $kcal = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 500)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?int $fiberG = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 20)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?string $waterL = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?int $waterMl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?string $imageFilename = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['nutrition:read', 'nutrition:write'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['nutrition:read'])]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, MealPhoto> Photos des repas du jour. */
    #[ORM\OneToMany(mappedBy: 'nutritionLog', targetEntity: MealPhoto::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $photos;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->photos = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getLoggedOn(): \DateTimeImmutable { return $this->loggedOn; }
    public function setLoggedOn(\DateTimeImmutable $d): self { $this->loggedOn = $d; return $this; }
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
    public function getWaterL(): ?string { return $this->waterL; }
    public function setWaterL(?string $v): self { $this->waterL = $v; return $this; }
    public function getWaterMl(): ?int { return $this->waterMl; }
    public function setWaterMl(?int $v): self { $this->waterMl = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
    public function getImageFilename(): ?string { return $this->imageFilename; }
    public function setImageFilename(?string $f): self { $this->imageFilename = $f; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, MealPhoto> */
    public function getPhotos(): Collection { return $this->photos; }

    public function addPhoto(MealPhoto $p): self
    {
        if (!$this->photos->contains($p)) {
            $this->photos->add($p);
            $p->setNutritionLog($this);
        }
        return $this;
    }

    public function removePhoto(MealPhoto $p): self
    {
        $this->photos->removeElement($p);
        return $this;
    }
}
