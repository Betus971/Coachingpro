<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProgramAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pivot Program <-> User. Un coach assigne un programme à un client (avec une date de début).
 * En MVP perso, tu t'assignes toi-même un programme.
 *
 * Permet de garder un historique : un client peut avoir plusieurs programmes successifs.
 */
#[ORM\Entity(repositoryClass: ProgramAssignmentRepository::class)]
#[ORM\Index(columns: ['user_id', 'is_active'], name: 'idx_assignment_active')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(securityPostDenormalize: "is_granted('CREATE', object)"),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['assignment:read']],
    denormalizationContext: ['groups' => ['assignment:write']],
)]
class ProgramAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['assignment:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Program::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['assignment:read', 'assignment:write'])]
    private Program $program;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['assignment:read', 'assignment:write'])]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['assignment:read', 'assignment:write'])]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['assignment:read', 'assignment:write'])]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    #[Groups(['assignment:read', 'assignment:write'])]
    private bool $isActive = true;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid { return $this->id; }
    public function getProgram(): Program { return $this->program; }
    public function setProgram(Program $p): self { $this->program = $p; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function setStartDate(\DateTimeImmutable $d): self { $this->startDate = $d; return $this; }
    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $d): self { $this->endDate = $d; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $a): self { $this->isActive = $a; return $this; }
}
