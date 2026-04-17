<?php

namespace App\Support;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;

final class StripeProviderSubscriptionCheckout
{
    public static function secretConfigured(): bool
    {
        $secret = config('services.stripe.secret');

        return is_string($secret) && trim($secret) !== '';
    }

    /**
     * Build Stripe Checkout line_items for a paid subscription plan.
     * Uses a dashboard Price ID when set; otherwise creates a recurring price inline (price_data).
     *
     * @return array<int, array<string, mixed>>|null Null when the plan is free or invalid.
     */
    public static function lineItemsForPlan(SubscriptionPlan $plan): ?array
    {
        if ((int) $plan->price_cents <= 0) {
            return null;
        }

        if (is_string($plan->stripe_price_id) && trim($plan->stripe_price_id) !== '') {
            return [
                [
                    'price' => trim($plan->stripe_price_id),
                    'quantity' => 1,
                ],
            ];
        }

        $currency = strtolower((string) ($plan->currency ?: 'usd'));
        $description = Str::limit(strip_tags((string) ($plan->description ?? '')), 450);
        $productData = array_filter([
            'name' => (string) $plan->name,
            'description' => $description !== '' ? $description : null,
        ]);

        return [
            [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => $productData,
                    'unit_amount' => (int) $plan->price_cents,
                    'recurring' => self::recurringFromBillingCycle($plan->billing_cycle),
                ],
                'quantity' => 1,
            ],
        ];
    }

    /**
     * @return array{interval: string, interval_count?: int}
     */
    public static function recurringFromBillingCycle(?string $cycle): array
    {
        return match ((string) $cycle) {
            'yearly' => ['interval' => 'year'],
            'quarterly' => ['interval' => 'month', 'interval_count' => 3],
            default => ['interval' => 'month'],
        };
    }

    /**
     * True when this active plan can be purchased or activated during onboarding.
     */
    public static function planIsSelectableForOnboarding(SubscriptionPlan $plan): bool
    {
        if ((int) $plan->price_cents <= 0) {
            return true;
        }

        return self::secretConfigured();
    }
}
