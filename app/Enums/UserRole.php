<?php

namespace App\Enums;

enum UserRole: string
{
    case USER = 'user';
    case PROVIDER = 'provider';
    case AFFILIATE = 'affiliate';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::USER => 'General User',
            self::PROVIDER => 'Service Provider',
            self::AFFILIATE => 'Affiliate Partner',
            self::ADMIN => 'Administrator',
            self::SUPER_ADMIN => 'Super Administrator',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::USER => 'Immigrants seeking services',
            self::PROVIDER => 'Professional service providers',
            self::AFFILIATE => 'Referral partners earning commissions',
            self::ADMIN => 'Platform administrators',
            self::SUPER_ADMIN => 'Full system access',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
