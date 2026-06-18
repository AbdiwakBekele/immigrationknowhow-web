<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Provider\SyncProviderStripeSubscription;
use App\Http\Controllers\Api\Mobile\Concerns\DetectsMobileClient;
use App\Http\Controllers\Controller;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Support\AppleIapConfig;
use App\Support\ProviderSubscriptionPromo;
use App\Support\StripeConfig;
use App\Support\StripeProviderSubscriptionCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class ProviderSubscriptionsController extends Controller
{
    use DetectsMobileClient;

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

        $pendingSubscription = ProviderSubscription::query()
            ->where('service_provider_id', $provider->id)
            ->where('status', 'incomplete')
            ->latest('id')
            ->with('plan')
            ->first();

        $hasActiveSubscription = $currentSubscription !== null;
        $hasPaidPlans = $plans->contains(fn (SubscriptionPlan $plan) => (int) $plan->price_cents > 0);

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
                'plans' => $plans->map(fn (SubscriptionPlan $plan) => array_merge($plan->toArray(), [
                    'apple_product_id' => (int) $plan->price_cents > 0 ? $plan->appleProductId() : null,
                ]))->values(),
                'current_subscription' => $currentSubscription,
                'pending_subscription' => $pendingSubscription,
                'has_active_subscription' => $hasActiveSubscription,
                'requires_subscription' => $hasPaidPlans && ! $hasActiveSubscription,
                'subscription_history' => $history,
                'stripe_billing_configured' => StripeConfig::hasSecretKey(),
                'apple_iap_configured' => AppleIapConfig::configured(),
                'subscription_billing_configured' => StripeConfig::hasSecretKey() || AppleIapConfig::configured(),
                'ios_requires_apple_iap' => true,
                'provider_subscription_promo' => ProviderSubscriptionPromo::promoPayload($provider),
            ],
        ]);
    }

    public function checkout(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        if ($blocked = $this->iosStripeCheckoutBlockedResponse(
            $request,
            'On iOS, subscribe with In-App Purchase in the app.',
        )) {
            return $blocked;
        }

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

            $provider->updateExistingColumns([
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
                'success_url' => route('mobile.provider-subscription.checkout-return', [], true).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('mobile.provider-subscription.checkout-return', [], true).'?checkout=cancelled',
                'line_items' => $lineItems,
                'metadata' => [
                    'app' => 'provider_subscription',
                    'provider_id' => (string) $provider->id,
                    'user_id' => (string) $request->user()->id,
                    'plan_uuid' => (string) $plan->uuid,
                    'plan_name' => (string) $plan->name,
                ],
                'subscription_data' => ProviderSubscriptionPromo::stripeSubscriptionData($provider, [
                    'provider_id' => (string) $provider->id,
                    'user_id' => (string) $request->user()->id,
                    'plan_uuid' => (string) $plan->uuid,
                    'app' => 'provider_subscription',
                ]),
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
                'checkout_session_id' => (string) $session->id,
            ],
        ]);
    }

    public function cancel(Request $request, ProviderSubscription $subscription): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.', 'errors' => (object) []], 403);
        }

        if (filled($subscription->apple_original_transaction_id) && blank($subscription->stripe_subscription_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription was purchased with the App Store. Manage cancellation in iPhone Settings → Apple ID → Subscriptions.',
                'errors' => (object) [],
            ], 422);
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

        if (filled($subscription->apple_original_transaction_id) && blank($subscription->stripe_subscription_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription was purchased with the App Store. Manage it in iPhone Settings → Apple ID → Subscriptions.',
                'errors' => (object) [],
            ], 422);
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

        if (filled($subscription->apple_original_transaction_id) && blank($subscription->stripe_subscription_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription was purchased with the App Store. Switch plans using In-App Purchase in the app or manage it in iPhone Settings → Apple ID → Subscriptions.',
                'errors' => (object) [],
            ], 422);
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

    public function confirmCheckout(Request $request, SyncProviderStripeSubscription $syncProviderSubscription): JsonResponse
    {
        if ($blocked = $this->iosStripeCheckoutBlockedResponse(
            $request,
            'On iOS, subscribe with In-App Purchase in the app.',
        )) {
            return $blocked;
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        if (! StripeConfig::hasSecretKey()) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured.',
                'errors' => (object) [],
            ], 422);
        }

        $provider = $this->resolveProvider($request);
        $sessionId = trim((string) $validated['session_id']);

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::retrieve($sessionId);
            $metadataUserId = (int) ($session->metadata['user_id'] ?? $session->client_reference_id ?? 0);
            if ($metadataUserId !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkout session does not match your account.',
                    'errors' => (object) [],
                ], 422);
            }

            $subscriptionId = is_string($session->subscription) ? $session->subscription : null;
            if (! $subscriptionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription could not be verified.',
                    'errors' => (object) [],
                ], 422);
            }

            $subscription = StripeSubscription::retrieve($subscriptionId);
            $record = $syncProviderSubscription($subscription, $provider->id);
            if (! $record || ! in_array((string) $record->status, ['trialing', 'active', 'past_due'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is still processing. Please wait a moment and try again.',
                    'errors' => (object) [],
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Could not confirm subscription: '.$e->getMessage()
                    : 'Could not confirm subscription. Please try again.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully.',
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
