<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Support\StripeConfig;
use App\Support\StripeProviderSubscriptionCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class ProviderSubscriptionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $provider = $this->resolveProvider($request);

        $types = is_array($provider->service_types) ? $provider->service_types : [];

        $plans = SubscriptionPlan::query()
            ->active()
            ->forProviderServiceTypeValues($types)
            ->with('serviceTypeOption:id,value,label')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        $currentSubscription = ProviderSubscription::query()
            ->where('service_provider_id', $provider->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latest('id')
            ->with('plan')
            ->first();

        $history = ProviderSubscription::query()
            ->where('service_provider_id', $provider->id)
            ->with(['plan', 'payments' => function ($query) {
                $query->latest('id')->limit(6);
            }])
            ->latest('id')
            ->limit(12)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'plans' => $plans,
                'current_subscription' => $currentSubscription,
                'subscription_history' => $history,
                'stripe_billing_configured' => StripeConfig::hasSecretKey(),
            ],
        ]);
    }

    public function checkout(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $provider = $this->resolveProvider($request);

        if (! in_array((string) $plan->status, ['active'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available.',
                'errors' => (object) [],
            ], 422);
        }

        $types = is_array($provider->service_types) ? $provider->service_types : [];
        if (! SubscriptionPlan::query()->whereKey($plan->id)->forProviderServiceTypeValues($types)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available for your service types.',
                'errors' => (object) [],
            ], 422);
        }

        if ((int) $plan->price_cents <= 0) {
            $providerSubscription = ProviderSubscription::query()->firstOrNew([
                'service_provider_id' => $provider->id,
                'subscription_plan_id' => $plan->id,
            ]);

            $providerSubscription->fill([
                'status' => 'active',
                'started_at' => $providerSubscription->started_at ?? now(),
                'current_period_start' => now(),
                'current_period_end' => null,
                'cancel_at_period_end' => false,
                'stripe_customer_id' => $provider->stripe_customer_id,
                'stripe_subscription_id' => null,
                'affiliate_id' => $request->user()?->referred_by_affiliate_id,
                'affiliate_referral_id' => $request->user()?->affiliate_referral_id,
                'affiliate_attribution_type' => 'first_touch',
                'meta' => array_merge((array) ($providerSubscription->meta ?? []), ['source' => 'free_plan_checkout_mobile']),
            ]);
            $providerSubscription->save();

            $provider->update([
                'subscription_plan' => $plan->name,
                'subscription_expires_at' => null,
                'stripe_subscription_status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Free plan activated.',
                'data' => [
                    'checkout_url' => null,
                    'free_plan_activated' => true,
                ],
            ]);
        }

        if (! StripeProviderSubscriptionCheckout::secretConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured.',
                'errors' => (object) [],
            ], 422);
        }

        $lineItems = StripeProviderSubscriptionCheckout::lineItemsForPlan($plan);
        if ($lineItems === null) {
            return response()->json([
                'success' => false,
                'message' => 'This plan does not require checkout.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::create([
                'mode' => 'subscription',
                'customer_email' => $request->user()->email,
                'client_reference_id' => (string) $request->user()->id,
                'success_url' => config('app.url').'/provider/subscriptions?checkout=success',
                'cancel_url' => config('app.url').'/provider/subscriptions?checkout=cancelled',
                'line_items' => $lineItems,
                'metadata' => [
                    'app' => 'provider_subscription',
                    'provider_id' => (string) $provider->id,
                    'user_id' => (string) $request->user()->id,
                    'plan_uuid' => (string) $plan->uuid,
                    'plan_name' => (string) $plan->name,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'provider_id' => (string) $provider->id,
                        'user_id' => (string) $request->user()->id,
                        'plan_uuid' => (string) $plan->uuid,
                        'app' => 'provider_subscription',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Subscription checkout could not start: '.$e->getMessage()
                    : 'Subscription checkout could not start. Please try again.',
                'errors' => (object) [],
            ], 422);
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Could not start subscription checkout.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'checkout_url' => $checkoutUrl,
                'free_plan_activated' => false,
            ],
        ]);
    }

    public function cancel(Request $request, ProviderSubscription $subscription): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.', 'errors' => (object) []], 403);
        }

        if (! is_string($subscription->stripe_subscription_id) || $subscription->stripe_subscription_id === '') {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found.',
                'errors' => (object) [],
            ], 422);
        }

        if (! StripeConfig::hasSecretKey()) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Could not cancel subscription: '.$e->getMessage()
                    : 'Could not cancel subscription right now. Please try again.',
                'errors' => (object) [],
            ], 422);
        }

        $subscription->update([
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription will cancel at period end.',
            'data' => (object) [],
        ]);
    }

    public function resume(Request $request, ProviderSubscription $subscription): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.', 'errors' => (object) []], 403);
        }

        if (! is_string($subscription->stripe_subscription_id) || $subscription->stripe_subscription_id === '') {
            return response()->json([
                'success' => false,
                'message' => 'No Stripe subscription found.',
                'errors' => (object) [],
            ], 422);
        }

        if (! StripeConfig::hasSecretKey()) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Could not resume subscription: '.$e->getMessage()
                    : 'Could not resume subscription right now. Please try again.',
                'errors' => (object) [],
            ], 422);
        }

        $subscription->update([
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription resumed.',
            'data' => (object) [],
        ]);
    }

    public function changePlan(Request $request, ProviderSubscription $subscription, SubscriptionPlan $plan): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.', 'errors' => (object) []], 403);
        }

        if (! is_string($subscription->stripe_subscription_id) || $subscription->stripe_subscription_id === '') {
            return response()->json([
                'success' => false,
                'message' => 'No Stripe subscription found.',
                'errors' => (object) [],
            ], 422);
        }
        if (! is_string($plan->stripe_price_id) || $plan->stripe_price_id === '') {
            return response()->json([
                'success' => false,
                'message' => 'Selected plan is not configured.',
                'errors' => (object) [],
            ], 422);
        }

        $types = is_array($provider->service_types) ? $provider->service_types : [];
        if (! SubscriptionPlan::query()->whereKey($plan->id)->forProviderServiceTypeValues($types)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available for your service types.',
                'errors' => (object) [],
            ], 422);
        }

        if (! StripeConfig::hasSecretKey()) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $stripeSub = StripeSubscription::retrieve($subscription->stripe_subscription_id);
            $itemId = $stripeSub->items->data[0]->id ?? null;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Could not load subscription: '.$e->getMessage()
                    : 'Could not load subscription right now. Please try again.',
                'errors' => (object) [],
            ], 422);
        }
        if (! is_string($itemId) || $itemId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Unable to update subscription items.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'items' => [[
                    'id' => $itemId,
                    'price' => $plan->stripe_price_id,
                ]],
                'metadata' => [
                    'plan_uuid' => $plan->uuid,
                    'provider_id' => (string) $provider->id,
                    'app' => 'provider_subscription',
                ],
                'proration_behavior' => 'create_prorations',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Could not change plan: '.$e->getMessage()
                    : 'Could not change plan right now. Please try again.',
                'errors' => (object) [],
            ], 422);
        }

        $subscription->update([
            'subscription_plan_id' => $plan->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan change scheduled successfully.',
            'data' => (object) [],
        ]);
    }

    private function resolveProvider(Request $request): ServiceProvider
    {
        $provider = $request->user()?->serviceProvider;
        abort_unless($provider, 403);

        return $provider;
    }
}
