<?php

namespace App\Actions\Provider;

use App\Models\ProviderSubscription;
use App\Models\ProviderSubscriptionPayment;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use Carbon\CarbonImmutable;
use Stripe\Invoice as StripeInvoice;
use Stripe\Stripe;
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

        // Fallback when Stripe doesn't return current_period_end (rare, but happens for incomplete objects)
        // so provider history always has a period end date.
        if ($periodEnd === null && $periodStart !== null && $plan) {
            $periodEnd = match ((string) $plan->billing_cycle) {
                'yearly' => $periodStart->addYear(),
                'quarterly' => $periodStart->addMonths(3),
                default => $periodStart->addMonth(),
            };
        }

        $payload = [
            'service_provider_id' => $provider->id,
            'subscription_plan_id' => $plan?->id,
            'status' => $isCanceled ? 'canceled' : $status,
            'started_at' => $periodStart,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false),
            'ended_at' => $isCanceled ? now() : null,
            'stripe_customer_id' => is_string($subscription->customer) ? $subscription->customer : null,
            'stripe_subscription_id' => (string) $subscription->id,
            'meta' => [
                'default_payment_method' => $subscription->default_payment_method ?? null,
            ],
            'affiliate_id' => $provider->user?->referred_by_affiliate_id,
            'affiliate_referral_id' => $provider->user?->affiliate_referral_id,
            'affiliate_attribution_type' => 'first_touch',
        ];

        // Avoid duplicate history rows for new providers:
        // onboarding creates an "incomplete" placeholder before Stripe Checkout exists.
        // When Stripe confirms, update that placeholder instead of creating a second row.
        $placeholder = ProviderSubscription::query()
            ->where('service_provider_id', $provider->id)
            ->whereNull('stripe_subscription_id')
            ->where('status', 'incomplete')
            ->when($plan?->id, fn ($q) => $q->where('subscription_plan_id', $plan->id))
            ->latest('id')
            ->first();

        if ($placeholder) {
            $placeholder->fill($payload);
            $placeholder->save();
            $providerSubscription = $placeholder;
        } else {
            $providerSubscription = ProviderSubscription::query()->updateOrCreate(
                ['stripe_subscription_id' => (string) $subscription->id],
                $payload
            );
        }

        $provider->updateExistingColumns([
            'stripe_customer_id' => is_string($subscription->customer) ? $subscription->customer : null,
            'stripe_subscription_id' => $subscription->id,
            'stripe_subscription_status' => $status,
            'stripe_current_period_end' => $periodEnd,
            'subscription_plan' => $isCanceled ? null : ($plan?->name ?? (string) config('services.stripe.provider_subscription_plan_name', 'provider_monthly')),
            'subscription_expires_at' => $periodEnd,
        ]);

        // Best-effort invoice sync so "Payments" isn't empty even if webhooks are delayed/misconfigured.
        try {
            $secret = (string) config('services.stripe.secret', '');
            if ($secret !== '') {
                Stripe::setApiKey($secret);
            }

            $invoices = StripeInvoice::all([
                'subscription' => (string) $subscription->id,
                'limit' => 6,
            ]);

            foreach ($invoices->data ?? [] as $invoice) {
                if (! isset($invoice->id) || ! is_string($invoice->id) || $invoice->id === '') {
                    continue;
                }

                $status = (string) ($invoice->status ?? '');
                $localStatus = match ($status) {
                    'paid' => 'paid',
                    'open' => 'open',
                    'void' => 'void',
                    'uncollectible' => 'failed',
                    default => 'open',
                };

                ProviderSubscriptionPayment::query()->updateOrCreate(
                    ['stripe_invoice_id' => (string) $invoice->id],
                    [
                        'provider_subscription_id' => $providerSubscription->id,
                        'service_provider_id' => $providerSubscription->service_provider_id,
                        'subscription_plan_id' => $providerSubscription->subscription_plan_id,
                        'stripe_payment_intent_id' => is_string($invoice->payment_intent ?? null) ? $invoice->payment_intent : null,
                        'amount_due_cents' => (int) ($invoice->amount_due ?? 0),
                        'amount_paid_cents' => (int) ($invoice->amount_paid ?? 0),
                        'currency' => strtoupper((string) ($invoice->currency ?? 'USD')),
                        'status' => $localStatus,
                        'billing_reason' => (string) ($invoice->billing_reason ?? ''),
                        'paid_at' => isset($invoice->status_transitions->paid_at) && is_numeric($invoice->status_transitions->paid_at)
                            ? now()->setTimestamp((int) $invoice->status_transitions->paid_at)
                            : null,
                        'invoice_pdf_url' => is_string($invoice->invoice_pdf ?? null) ? $invoice->invoice_pdf : null,
                        'raw_payload' => method_exists($invoice, 'toArray') ? $invoice->toArray() : null,
                    ]
                );
            }
        } catch (\Throwable) {
            // ignore invoice sync errors
        }

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
