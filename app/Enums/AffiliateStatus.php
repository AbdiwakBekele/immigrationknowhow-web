<?php

namespace App\Enums;

enum AffiliateStatus: string
{
    case INVITED = 'invited';
    case EMAIL_SENT = 'email_sent';
    case PENDING_VERIFICATION = 'pending_verification';
    case VERIFIED = 'verified';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invited',
            self::EMAIL_SENT => 'Email Sent',
            self::PENDING_VERIFICATION => 'Pending Verification',
            self::VERIFIED => 'Verified',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INVITED => 'slate',
            self::EMAIL_SENT => 'sky',
            self::PENDING_VERIFICATION => 'amber',
            self::VERIFIED => 'emerald',
            self::ACTIVE => 'green',
            self::INACTIVE => 'red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
