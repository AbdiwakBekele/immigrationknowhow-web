<?php

namespace App\Enums;

enum ContractState: string
{
    case DRAFT = 'draft';
    case OFFERED = 'offered';
    case WITHDRAWN = 'withdrawn';
    case ACCEPTED = 'accepted';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case ENDED = 'ended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::OFFERED => 'Offered',
            self::WITHDRAWN => 'Withdrawn',
            self::ACCEPTED => 'Accepted',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::ENDED => 'Ended',
            self::CANCELLED => 'Cancelled',
        };
    }
}
