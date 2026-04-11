<?php

namespace App\Enums;

enum AffiliateCommissionTrigger: string
{
    case SIGNUP = 'signup';
    case LEAD_CONVERTED = 'lead_converted';

    public function label(): string
    {
        return match ($this) {
            self::SIGNUP => 'Signup',
            self::LEAD_CONVERTED => 'Lead Converted',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
