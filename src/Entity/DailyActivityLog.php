<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\DailyActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Journal d'activité quotidienne (Pas, NEAT, Sommeil...).
 * Alimenté manuellement par le client ou automatiquement via API (ex: Google Fit).
 */
#[ORM\Entity(repositoryClass: DailyActivityLogRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_activity_user_date', columns: ['user_id', 'logged_on'])]
#[ORM\Index(columns: ['user_id', 'logged_on'], name: 'idx_activity_user_date')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('ROLE_USER')", processor: 'App\State\CurrentUserOwnershipProcessor'),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['activity:read']],
    denormalizationContext: ['groups' => ['activity:write']],
    order: ['loggedOn' => 'DESC'],
)]
class DailyActivityLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['activity:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['activity:read'])]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['activity:read', 'activity:write'])]
    private \DateTimeImmutable $loggedOn;

    /** Nombre de pas dans la journée (Google Fit / Samsung Health) */
    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 150000)]
    #[Groups(['activity:read', 'activity:write'])]
    private int $stepCount = 0;

    /** Calories actives brûlées (NEAT + Sport) en dehors du BMR */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 10000)]
    #[Groups(['activity:read', 'activity:write'])]
    private ?int $activeCalories = null;

    /** Durée totale du sommeil en minutes (pratique pour l'API Samsung/Google) */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 1440)]
    #[Groups(['activity:read', 'activity:write'])]
    private ?int $sleepMinutes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['activity:read', 'activity:write'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['activity:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $u): self
    {
        $this->user = $u;
        return $this;
    }

    public function getLoggedOn(): \DateTimeImmutable
    {
        return $this->loggedOn;
    }

    public function setLoggedOn(\DateTimeImmutable $d): self
    {
        $this->loggedOn = $d;
        return $this;
    }

    public function getStepCount(): int
    {
        return $this->stepCount;
    }

    public function setStepCount(int $v): self
    {
        $this->stepCount = $v;
        return $this;
    }

    public function getActiveCalories(): ?int
    {
        return $this->activeCalories;
    }

    public function setActiveCalories(?int $v): self
    {
        $this->activeCalories = $v;
        return $this;
    }

    public function getSleepMinutes(): ?int
    {
        return $this->sleepMinutes;
    }

    public function setSleepMinutes(?int $v): self
    {
        $this->sleepMinutes = $v;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $n): self
    {
        $this->notes = $n;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
