<?php

namespace App\Support;

use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;

final class ProviderSubscriptionPromo
{
    public static function trialMonths(): int
    {
        return max(0, (int) config('subscriptions.provider_trial_months', 6));
    }

    public static function trialDays(): int
    {
        return self::trialMonths() * 30;
    }

    public static function isTrialEligible(?ServiceProvider $provider): bool
    {
        if (self::trialMonths() <= 0) {
            return false;
        }

        if ($provider === null) {
            return true;
        }

        return ! ProviderSubscription::query()
            ->where('service_provider_id', $provider->id)
            ->where(function ($query): void {
                $query->whereNotNull('stripe_subscription_id')
                    ->orWhereNotNull('apple_original_transaction_id');
            })
            ->exists();
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array<string, mixed>
     */
    public static function stripeSubscriptionData(?ServiceProvider $provider, array $metadata): array
    {
        $data = ['metadata' => $metadata];

        if (self::isTrialEligible($provider)) {
            $data['trial_period_days'] = self::trialDays();
        }

        return $data;
    }

    /**
     * @return array{trial_months: int, trial_eligible: bool}
     */
    public static function promoPayload(?ServiceProvider $provider = null): array
    {
        return [
            'trial_months' => self::trialMonths(),
            'trial_eligible' => self::isTrialEligible($provider),
        ];
    }
}
