<?php

declare(strict_types=1);

namespace App\Enum;

enum InvitationStatus: string
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Revoked  = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'En attente',
            self::Accepted => 'Acceptée',
            self::Revoked  => 'Révoquée',
        };
    }
}
