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
 * Pesée quotidienne. Une entrée par jour par utilisateur (contrainte unique).
 * Toutes les opérations passent par le CurrentUserExtension qui scope au user courant
 * (ou à ses clients s'il est coach).
 */
#[ORM\Entity(repositoryClass: WeightLogRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_weight_user_date', columns: ['user_id', 'logged_on'])]
#[ORM\Index(columns: ['user_id', 'logged_on'], name: 'idx_weight_user_date')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(securityPostDenormalize: "is_granted('CREATE', object)"),
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
    #[Groups(['weight:read', 'weight:write'])]
    private User $user;

    /**
     * Stocké en string (decimal Doctrine) pour conserver la précision PostgreSQL numeric(5,2).
     * 999.99 kg max — large.
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Range(min: 30, max: 350)]
    #[Groups(['weight:read', 'weight:write'])]
    private string $weightKg;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['weight:read', 'weight:write'])]
    private \DateTimeImmutable $loggedOn;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['weight:read', 'weight:write'])]
    private ?string $notes = null;

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
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
