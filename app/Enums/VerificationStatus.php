<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Submission',
            self::UNDER_REVIEW => 'Under Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::UNDER_REVIEW => 'blue',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::EXPIRED => 'gray',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-100 text-amber-800',
            self::UNDER_REVIEW => 'bg-blue-100 text-blue-800',
            self::APPROVED => 'bg-emerald-100 text-emerald-800',
            self::REJECTED => 'bg-red-100 text-red-800',
            self::EXPIRED => 'bg-gray-100 text-gray-800',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
