<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\WeightLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pesée quotidienne + composition corporelle (optionnel, source Boditrax ou saisie manuelle).
 * Une entrée par jour par utilisateur (contrainte unique).
 */
#[ORM\Entity(repositoryClass: WeightLogRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_weight_user_date', columns: ['user_id', 'logged_on'])]
#[ORM\Index(columns: ['user_id', 'logged_on'], name: 'idx_weight_user_date')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('ROLE_USER')", processor: 'App\State\CurrentUserOwnershipProcessor'),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['weight:read']],
    denormalizationContext: ['groups' => ['weight:write']],
    order: ['loggedOn' => 'DESC'],
)]
class WeightLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['weight:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['weight:read'])]
    private User $user;

    /** Poids total en kg */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Range(min: 30, max: 350)]
    #[Groups(['weight:read', 'weight:write'])]
    private string $weightKg;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['weight:read', 'weight:write'])]
    private \DateTimeImmutable $loggedOn;

    // ── Champs Boditrax (composition corporelle — tous nullable) ────────────

    /** Masse grasse en % */
    #[ORM\Column(type: 'decimal', precision: 4, scale: 1, nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $fatPercent = null;

    /** Masse grasse en kg */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $fatKg = null;

    /** Masse musculaire en kg */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $muscleKg = null;

    /** Masse osseuse en kg */
    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $boneKg = null;

    /** Eau corporelle en % */
    #[ORM\Column(type: 'decimal', precision: 4, scale: 1, nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $waterPercent = null;

    /** Indice de masse corporelle */
    #[ORM\Column(type: 'decimal', precision: 4, scale: 1, nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $bmi = null;

    /** Métabolisme basal (kcal) */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?int $bmr = null;

    /** Source : 'manual', 'boditrax_csv' */
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['weight:read'])]
    private ?string $source = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $imageFilename = null;

    #[ORM\Column]
    #[Groups(['weight:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getWeightKg(): string { return $this->weightKg; }
    public function setWeightKg(string $w): self { $this->weightKg = $w; return $this; }
    public function getLoggedOn(): \DateTimeImmutable { return $this->loggedOn; }
    public function setLoggedOn(\DateTimeImmutable $d): self { $this->loggedOn = $d; return $this; }
    public function getFatPercent(): ?string { return $this->fatPercent; }
    public function setFatPercent(?string $v): self { $this->fatPercent = $v; return $this; }
    public function getFatKg(): ?string { return $this->fatKg; }
    public function setFatKg(?string $v): self { $this->fatKg = $v; return $this; }
    public function getMuscleKg(): ?string { return $this->muscleKg; }
    public function setMuscleKg(?string $v): self { $this->muscleKg = $v; return $this; }
    public function getBoneKg(): ?string { return $this->boneKg; }
    public function setBoneKg(?string $v): self { $this->boneKg = $v; return $this; }
    public function getWaterPercent(): ?string { return $this->waterPercent; }
    public function setWaterPercent(?string $v): self { $this->waterPercent = $v; return $this; }
    public function getBmi(): ?string { return $this->bmi; }
    public function setBmi(?string $v): self { $this->bmi = $v; return $this; }
    public function getBmr(): ?int { return $this->bmr; }
    public function setBmr(?int $v): self { $this->bmr = $v; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): self { $this->source = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
    public function getImageFilename(): ?string { return $this->imageFilename; }
    public function setImageFilename(?string $f): self { $this->imageFilename = $f; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
