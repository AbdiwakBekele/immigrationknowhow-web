<?php

namespace App\Actions\Provider;

use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;

final class FulfillProviderStripeCheckout
{
    /**
     * Link Checkout completion to the local provider subscription record.
     */
    public function __invoke(Session $session): bool
    {
        if (($session->metadata['app'] ?? '') !== 'provider_subscription') {
            return false;
        }

        $providerId = (int) ($session->metadata['provider_id'] ?? 0);
        $planUuid = (string) ($session->metadata['plan_uuid'] ?? '');

        if ($providerId < 1 || $planUuid === '') {
            return false;
        }

        $subscriptionId = $session->subscription;
        if (is_object($subscriptionId) && isset($subscriptionId->id)) {
            $subscriptionId = $subscriptionId->id;
        }
        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return false;
        }

        $customerId = $session->customer;
        if (is_object($customerId) && isset($customerId->id)) {
            $customerId = $customerId->id;
        }
        $customerId = is_string($customerId) ? $customerId : null;

        $plan = SubscriptionPlan::query()->where('uuid', $planUuid)->first();
        if (! $plan) {
            return false;
        }

        return DB::transaction(function () use ($providerId, $plan, $session, $subscriptionId, $customerId) {
            $record = ProviderSubscription::query()
                ->where('service_provider_id', $providerId)
                ->where('subscription_plan_id', $plan->id)
                ->latest('id')
                ->first();

            if (! $record) {
                return false;
            }

            $record->update([
                'stripe_checkout_session_id' => $session->id,
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $subscriptionId,
                'status' => 'active',
                'started_at' => $record->started_at ?? now(),
                'current_period_start' => $record->current_period_start ?? now(),
            ]);

            $provider = ServiceProvider::query()->find($providerId);
            if ($provider) {
                $provider->update([
                    'stripe_customer_id' => $customerId,
                    'stripe_subscription_id' => $subscriptionId,
                    'stripe_subscription_status' => 'active',
                    'subscription_plan' => $plan->name,
                ]);
            }

            return true;
        });
    }
}
