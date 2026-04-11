<?php

namespace App\Enums;

enum AffiliateCommissionType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixed Amount',
            self::PERCENTAGE => 'Percentage',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
