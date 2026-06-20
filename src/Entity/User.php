<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single-table user. Role-based discrimination Coach vs Client.
 * Un Client a un `coach` assigné (nullable). Un Coach voit ses `clients`.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\Index(columns: ['coach_id'], name: 'idx_user_coach')]
#[UniqueEntity('email')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new GetCollection(security: "is_granted('ROLE_COACH')"),
        // POST public = inscription self-service. À retirer / remplacer par un endpoint d'invitation côté coach plus tard.
        new Post(
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: 'App\State\UserPasswordHasher',
        ),
        new Patch(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_COACH = 'ROLE_COACH';
    public const ROLE_CLIENT = 'ROLE_CLIENT';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['user:read'])]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:write'])]
    private string $email;

    /** @var list<string> */
    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 8, groups: ['user:create'])]
    #[Groups(['user:write'])]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'user:write'])]
    private string $firstName;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'user:write'])]
    private string $lastName;

    /**
     * Le coach qui suit ce client. Null si l'utilisateur EST coach (ou solo).
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'clients')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['user:read'])]
    private ?self $coach = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'coach', targetEntity: self::class)]
    private Collection $clients;

    // Données morphométriques pour calculs (BMR, TDEE...)
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?int $heightCm = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(choices: ['male', 'female', 'other'])]
    #[Groups(['user:read', 'user:write'])]
    private ?string $sex = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?\DateTimeImmutable $birthDate = null;

    // ── Gamification ────────────────────────────────────────────────────────
    #[ORM\Column(options: ["default" => 0])]
    #[Groups(['user:read'])]
    private int $currentStreak = 0;

    #[ORM\Column(options: ["default" => 0])]
    #[Groups(['user:read'])]
    private int $longestStreak = 0;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastActiveDate = null;

    // ── Google Fit OAuth tokens ─────────────────────────────────────────────
    // Volontairement HORS des groupes Serializer : ces tokens ne doivent JAMAIS
    // sortir via l'API. Chiffrer en prod (kernel.secret + Sodium) si on veut être propre.

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleAccessToken = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleRefreshToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $googleAccessExpiresAt = null;

    /** Exposé en lecture seule pour que le front sache si la connexion Google est active. */
    #[Groups(['user:read'])]
    public function isGoogleFitConnected(): bool
    {
        return $this->googleRefreshToken !== null;
    }

    #[ORM\Column]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->clients = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $p): self
    {
        $this->plainPassword = $p;
        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $f): self
    {
        $this->firstName = $f;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $l): self
    {
        $this->lastName = $l;
        return $this;
    }

    public function getCoach(): ?self
    {
        return $this->coach;
    }

    public function setCoach(?self $c): self
    {
        $this->coach = $c;
        return $this;
    }

    /** @return Collection<int, self> */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function getHeightCm(): ?int
    {
        return $this->heightCm;
    }

    public function setHeightCm(?int $h): self
    {
        $this->heightCm = $h;
        return $this;
    }

    public function getSex(): ?string
    {
        return $this->sex;
    }

    public function setSex(?string $s): self
    {
        $this->sex = $s;
        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $d): self
    {
        $this->birthDate = $d;
        return $this;
    }

    /** Âge en années depuis la date de naissance (null si non renseignée). */
    public function getAge(): ?int
    {
        return $this->birthDate?->diff(new \DateTimeImmutable('today'))->y;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCurrentStreak(): int
    {
        return $this->currentStreak;
    }

    public function setCurrentStreak(int $currentStreak): self
    {
        $this->currentStreak = $currentStreak;
        return $this;
    }

    public function getLongestStreak(): int
    {
        return $this->longestStreak;
    }

    public function setLongestStreak(int $longestStreak): self
    {
        $this->longestStreak = $longestStreak;
        return $this;
    }

    public function getLastActiveDate(): ?\DateTimeImmutable
    {
        return $this->lastActiveDate;
    }

    public function setLastActiveDate(?\DateTimeImmutable $lastActiveDate): self
    {
        $this->lastActiveDate = $lastActiveDate;
        return $this;
    }

    public function isCoach(): bool
    {
        return in_array(self::ROLE_COACH, $this->roles, true);
    }

    public function isClientOf(self $coach): bool
    {
        return $this->coach?->getId()->equals($coach->getId()) ?? false;
    }

    // ── Google Fit tokens (getters/setters internes) ────────────────────────
    public function getGoogleAccessToken(): ?string
    {
        return $this->googleAccessToken;
    }

    public function setGoogleAccessToken(?string $token): self
    {
        $this->googleAccessToken = $token;
        return $this;
    }

    public function getGoogleRefreshToken(): ?string
    {
        return $this->googleRefreshToken;
    }

    public function setGoogleRefreshToken(?string $token): self
    {
        $this->googleRefreshToken = $token;
        return $this;
    }

    public function getGoogleAccessExpiresAt(): ?\DateTimeImmutable
    {
        return $this->googleAccessExpiresAt;
    }

    public function setGoogleAccessExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->googleAccessExpiresAt = $expiresAt;
        return $this;
    }

    /** Considère expiré si déjà passé OU si expire dans les 60s (marge de sécurité). */
    public function isGoogleAccessTokenExpired(): bool
    {
        if ($this->googleAccessToken === null || $this->googleAccessExpiresAt === null) {
            return true;
        }
        return $this->googleAccessExpiresAt->getTimestamp() <= (time() + 60);
    }

    /** Reset complet : appelé quand l'utilisateur déconnecte Google Fit. */
    public function disconnectGoogleFit(): self
    {
        $this->googleAccessToken = null;
        $this->googleRefreshToken = null;
        $this->googleAccessExpiresAt = null;
        return $this;
    }
}
