<?php

namespace App\Enums;

enum AffiliateCommissionTrigger: string
{
    case SIGNUP = 'signup';
    case LEAD_CONVERTED = 'lead_converted';
    case PROVIDER_SUBSCRIPTION_INITIAL = 'provider_subscription_initial';
    case PROVIDER_SUBSCRIPTION_RECURRING = 'provider_subscription_recurring';

    public function label(): string
    {
        return match ($this) {
            self::SIGNUP => 'Signup',
            self::LEAD_CONVERTED => 'Lead Converted',
            self::PROVIDER_SUBSCRIPTION_INITIAL => 'Provider Subscription (Initial)',
            self::PROVIDER_SUBSCRIPTION_RECURRING => 'Provider Subscription (Recurring)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
