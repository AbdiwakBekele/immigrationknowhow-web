<?php

namespace App\Actions\Provider;

use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use Carbon\CarbonImmutable;
use Stripe\Subscription;

final class SyncProviderStripeSubscription
{
    public function __invoke(Subscription $subscription, ?int $providerId = null): ?ProviderSubscription
    {
        $provider = $this->resolveProvider($subscription, $providerId);
        if (! $provider) {
            return null;
        }

        $status = (string) $subscription->status;
        $periodStart = is_numeric($subscription->current_period_start)
            ? CarbonImmutable::createFromTimestampUTC((int) $subscription->current_period_start)
            : null;
        $periodEnd = is_numeric($subscription->current_period_end)
            ? CarbonImmutable::createFromTimestampUTC((int) $subscription->current_period_end)
            : null;

        $isCanceled = in_array($status, ['canceled', 'incomplete_expired', 'unpaid'], true);
        $plan = $this->resolvePlan($subscription);

        $providerSubscription = ProviderSubscription::query()->updateOrCreate(
            ['stripe_subscription_id' => $subscription->id],
            [
                'service_provider_id' => $provider->id,
                'subscription_plan_id' => $plan?->id,
                'status' => $isCanceled ? 'canceled' : $status,
                'started_at' => $periodStart,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false),
                'ended_at' => $isCanceled ? now() : null,
                'stripe_customer_id' => is_string($subscription->customer) ? $subscription->customer : null,
                'meta' => [
                    'default_payment_method' => $subscription->default_payment_method ?? null,
                ],
                'affiliate_id' => $provider->user?->referred_by_affiliate_id,
                'affiliate_referral_id' => $provider->user?->affiliate_referral_id,
                'affiliate_attribution_type' => 'first_touch',
            ]
        );

        $provider->update([
            'stripe_customer_id' => is_string($subscription->customer) ? $subscription->customer : null,
            'stripe_subscription_id' => $subscription->id,
            'stripe_subscription_status' => $status,
            'stripe_current_period_end' => $periodEnd,
            'subscription_plan' => $isCanceled ? null : ($plan?->name ?? (string) config('services.stripe.provider_subscription_plan_name', 'provider_monthly')),
            'subscription_expires_at' => $periodEnd,
        ]);

        return $providerSubscription;
    }

    private function resolveProvider(Subscription $subscription, ?int $providerId): ?ServiceProvider
    {
        if ($providerId && $providerId > 0) {
            return ServiceProvider::query()->find($providerId);
        }

        $metadataProviderId = (int) ($subscription->metadata['provider_id'] ?? 0);
        if ($metadataProviderId > 0) {
            return ServiceProvider::query()->find($metadataProviderId);
        }

        if (is_string($subscription->id) && $subscription->id !== '') {
            $bySubscription = ServiceProvider::query()
                ->where('stripe_subscription_id', $subscription->id)
                ->first();

            if ($bySubscription) {
                return $bySubscription;
            }
        }

        if (is_string($subscription->customer) && $subscription->customer !== '') {
            return ServiceProvider::query()
                ->where('stripe_customer_id', $subscription->customer)
                ->first();
        }

        return null;
    }

    private function resolvePlan(Subscription $subscription): ?SubscriptionPlan
    {
        $planUuid = (string) ($subscription->metadata['plan_uuid'] ?? '');
        if ($planUuid !== '') {
            $plan = SubscriptionPlan::query()->where('uuid', $planUuid)->first();
            if ($plan) {
                return $plan;
            }
        }

        $priceId = $subscription->items->data[0]->price->id ?? null;
        if (is_string($priceId) && $priceId !== '') {
            return SubscriptionPlan::query()->where('stripe_price_id', $priceId)->first();
        }

        return null;
    }
}
