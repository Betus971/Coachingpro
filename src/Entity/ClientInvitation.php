<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\InvitationStatus;
use App\Repository\ClientInvitationRepository;
use App\State\ClientInvitationProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Invitation d'un client par un coach (onboarding par email).
 *
 * Flux : le coach crée une invitation (POST API ou form Twig) -> un token unique
 * est généré et un email part avec un lien /invitation/{token}. Le client ouvre
 * le lien, définit son mot de passe -> un User ROLE_CLIENT est créé et rattaché
 * au coach. L'invitation passe alors Accepted (append-only, auditable).
 *
 * Scoping : un coach ne voit que SES invitations (CurrentUserExtension + security).
 */
#[ORM\Entity(repositoryClass: ClientInvitationRepository::class)]
#[ORM\Table(name: 'client_invitation')]
#[ORM\Index(columns: ['coach_id'], name: 'idx_invitation_coach')]
#[ORM\UniqueConstraint(name: 'uniq_invitation_token', columns: ['token'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_COACH')"),
        new Get(security: "is_granted('ROLE_COACH') and object.getCoach() == user"),
        new Post(security: "is_granted('ROLE_COACH')", processor: ClientInvitationProcessor::class),
        new Patch(security: "is_granted('ROLE_COACH') and object.getCoach() == user"),
    ],
    normalizationContext: ['groups' => ['invitation:read']],
    denormalizationContext: ['groups' => ['invitation:write']],
    order: ['createdAt' => 'DESC'],
)]
class ClientInvitation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['invitation:read'])]
    private Uuid $id;

    /** Le coach qui invite (propriétaire de l'invitation). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['invitation:read'])]
    private User $coach;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['invitation:read', 'invitation:write'])]
    private string $email;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['invitation:read', 'invitation:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['invitation:read', 'invitation:write'])]
    private ?string $lastName = null;

    /** Token aléatoire du lien d'acceptation. Jamais modifiable par l'API. */
    #[ORM\Column(length: 64)]
    #[Groups(['invitation:read'])]
    private string $token;

    #[ORM\Column(enumType: InvitationStatus::class)]
    #[Groups(['invitation:read', 'invitation:write'])]
    private InvitationStatus $status = InvitationStatus::Pending;

    #[ORM\Column]
    #[Groups(['invitation:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['invitation:read'])]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['invitation:read'])]
    private ?\DateTimeImmutable $acceptedAt = null;

    /** Le compte client créé lors de l'acceptation. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $acceptedUser = null;

    public function __construct()
    {
        $this->id        = Uuid::v7();
        $this->token     = bin2hex(random_bytes(24));
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+14 days');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isUsable(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    public function accept(User $client): self
    {
        $this->status       = InvitationStatus::Accepted;
        $this->acceptedAt   = new \DateTimeImmutable();
        $this->acceptedUser = $client;
        return $this;
    }

    public function revoke(): self
    {
        $this->status = InvitationStatus::Revoked;
        return $this;
    }

    public function getId(): Uuid { return $this->id; }
    public function getCoach(): User { return $this->coach; }
    public function setCoach(User $c): self { $this->coach = $c; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $e): self { $this->email = $e; return $this; }
    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $f): self { $this->firstName = $f; return $this; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $l): self { $this->lastName = $l; return $this; }
    public function getToken(): string { return $this->token; }
    public function getStatus(): InvitationStatus { return $this->status; }
    public function setStatus(InvitationStatus $s): self { $this->status = $s; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }
    public function getAcceptedUser(): ?User { return $this->acceptedUser; }
}
