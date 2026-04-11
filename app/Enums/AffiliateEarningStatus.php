<?php

namespace App\Enums;

enum AffiliateEarningStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid',
            self::REJECTED => 'Rejected',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
