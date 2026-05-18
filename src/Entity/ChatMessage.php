<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_message')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_chat_user_date')]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** 'user' ou 'model' (convention Gemini) */
    #[ORM\Column(length: 10)]
    private string $role;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $role, string $content)
    {
        $this->user      = $user;
        $this->role      = $role;
        $this->content   = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }

    public function getRole(): string { return $this->role; }

    public function getContent(): string { return $this->content; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
