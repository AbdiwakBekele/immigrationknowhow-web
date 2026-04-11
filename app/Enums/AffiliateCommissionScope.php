<?php

namespace App\Enums;

enum AffiliateCommissionScope: string
{
    case GLOBAL = 'global';
    case AFFILIATE = 'affiliate';
    case REFERRED_USER = 'referred_user';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global Default',
            self::AFFILIATE => 'Affiliate Override',
            self::REFERRED_USER => 'Referred User Override',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
