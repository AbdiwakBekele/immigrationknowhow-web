<?php

namespace App\Http\Controllers\Provider;

use App\Actions\Provider\SyncProviderStripeSubscription;
use App\Http\Controllers\Controller;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Support\ProviderSubscriptionPromo;
use App\Support\StripeConfig;
use App\Support\StripeProviderSubscriptionCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class SubscriptionController extends Controller
{
    public function index(Request $request, SyncProviderStripeSubscription $syncProviderSubscription): Response|RedirectResponse
    {
        $provider = $this->resolveProvider($request);

        $checkoutStatus = (string) $request->query('checkout', '');
        $checkoutSessionId = (string) $request->query('session_id', '');

        if ($checkoutStatus === 'success' && $checkoutSessionId !== '') {
            if (! StripeConfig::hasSecretKey()) {
                return redirect()
                    ->route('provider.subscriptions.index')
                    ->with('error', 'Stripe is not configured.');
            }

            try {
                Stripe::setApiKey((string) config('services.stripe.secret'));
                $session = StripeCheckoutSession::retrieve($checkoutSessionId);
                $subscriptionId = is_string($session->subscription) ? $session->subscription : null;
                if (! $subscriptionId) {
                    return redirect()
                        ->route('provider.subscriptions.index')
                        ->with('error', 'Subscription could not be verified.');
                }

                $subscription = StripeSubscription::retrieve($subscriptionId);
                $syncProviderSubscription($subscription, $provider->id);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('provider.subscriptions.index')
                    ->with('error', config('app.debug')
                        ? 'Could not confirm subscription: '.$e->getMessage()
                        : 'Could not confirm subscription. Please refresh and try again.');
            }

            return redirect()
                ->route('provider.subscriptions.index')
                ->with('success', 'Subscription activated successfully.');
        }

        if ($checkoutStatus === 'cancelled') {
            return redirect()
                ->route('provider.subscriptions.index')
                ->with('info', 'Checkout cancelled.');
        }

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

        $stripeBillingConfigured = StripeConfig::hasSecretKey();

        return Inertia::render('Provider/Subscriptions/Index', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
            'subscriptionHistory' => $history,
            'stripeBillingConfigured' => $stripeBillingConfigured,
            'showStripeSetupHints' => (bool) config('app.debug'),
            'providerSubscriptionPromo' => ProviderSubscriptionPromo::promoPayload($provider),
        ]);
    }

    public function checkout(Request $request, SubscriptionPlan $plan): RedirectResponse|SymfonyResponse
    {
        $provider = $this->resolveProvider($request);

        if (! in_array((string) $plan->status, ['active'], true)) {
            return back()->with('error', 'This plan is not available.');
        }

        $types = is_array($provider->service_types) ? $provider->service_types : [];
        if (! SubscriptionPlan::query()->whereKey($plan->id)->forProviderServiceTypeValues($types)->exists()) {
            return back()->with('error', 'This plan is not available for your service types.');
        }

        if ((int) $plan->price_cents <= 0) {
            $providerSubscription = ProviderSubscription::query()->firstOrNew([
                'service_provider_id' => $provider->id,
                'subscription_plan_id' => $plan->id,
            ]);

            $periodStart = now();
            $periodEnd = match ((string) $plan->billing_cycle) {
                'yearly' => $periodStart->copy()->addYear(),
                'quarterly' => $periodStart->copy()->addMonths(3),
                default => $periodStart->copy()->addMonth(),
            };

            $providerSubscription->fill([
                'status' => 'active',
                'started_at' => $providerSubscription->started_at ?? now(),
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
                'stripe_customer_id' => $provider->stripe_customer_id,
                'stripe_subscription_id' => null,
                'affiliate_id' => $request->user()?->referred_by_affiliate_id,
                'affiliate_referral_id' => $request->user()?->affiliate_referral_id,
                'affiliate_attribution_type' => 'first_touch',
                'meta' => array_merge((array) ($providerSubscription->meta ?? []), ['source' => 'free_plan_checkout']),
            ]);
            $providerSubscription->save();

            $provider->update([
                'subscription_plan' => $plan->name,
                'subscription_expires_at' => $periodEnd,
                'stripe_subscription_status' => 'active',
            ]);

            return back()->with('success', 'Free plan activated.');
        }

        if (! StripeProviderSubscriptionCheckout::secretConfigured()) {
            return back()->with('error', 'Stripe is not configured.');
        }

        $lineItems = StripeProviderSubscriptionCheckout::lineItemsForPlan($plan);
        if ($lineItems === null) {
            return back()->with('error', 'This plan does not require checkout.');
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::create([
                'mode' => 'subscription',
                'customer_email' => $request->user()->email,
                'client_reference_id' => (string) $request->user()->id,
                'success_url' => route('provider.subscriptions.index', [], true).'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('provider.subscriptions.index', [], true).'?checkout=cancelled',
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
            return back()->with('error', config('app.debug')
                ? 'Subscription checkout could not start: '.$e->getMessage()
                : 'Subscription checkout could not start. Please try again.');
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return back()->with('error', 'Could not start subscription checkout.');
        }

        return Inertia::location($checkoutUrl);
    }

    public function changePlan(Request $request, ProviderSubscription $subscription, string $planUuid): RedirectResponse
    {
        $provider = $this->resolveProvider($request);
        $plan = SubscriptionPlan::query()->where('uuid', $planUuid)->firstOrFail();
        Log::channel(config('logging.default'))->info('ProviderSubscription changePlan request', [
            'user_id' => $request->user()?->id,
            'provider_id' => $provider->id,
            'request_path' => $request->path(),
            'request_full_url' => $request->fullUrl(),
            'route_params' => [
                'subscription_uuid' => $subscription->uuid ?? null,
                'plan_uuid' => $planUuid,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'service_provider_id' => $subscription->service_provider_id,
                'status' => $subscription->status,
                'stripe_subscription_id_present' => filled($subscription->stripe_subscription_id),
            ],
            'plan' => [
                'id' => $plan->id,
                'price_cents' => $plan->price_cents,
                'stripe_price_id_present' => filled($plan->stripe_price_id),
            ],
        ]);

        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            Log::channel(config('logging.default'))->warning('ProviderSubscription changePlan forbidden', [
                'provider_id' => $provider->id,
                'subscription_service_provider_id' => $subscription->service_provider_id,
            ]);
            abort(403);
        }

        if (! is_string($subscription->stripe_subscription_id) || $subscription->stripe_subscription_id === '') {
            return back()->with('error', 'No Stripe subscription found.');
        }
        if (! is_string($plan->stripe_price_id) || $plan->stripe_price_id === '') {
            return back()->with('error', 'Selected plan is not configured.');
        }

        $types = is_array($provider->service_types) ? $provider->service_types : [];
        if (! SubscriptionPlan::query()->whereKey($plan->id)->forProviderServiceTypeValues($types)->exists()) {
            return back()->with('error', 'This plan is not available for your service types.');
        }

        if (! StripeConfig::hasSecretKey()) {
            return back()->with('error', 'Stripe is not configured.');
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $stripeSub = StripeSubscription::retrieve($subscription->stripe_subscription_id);
            $itemId = $stripeSub->items->data[0]->id ?? null;
        } catch (\Throwable $e) {
            Log::channel(config('logging.default'))->warning('ProviderSubscription changePlan failed retrieving stripe subscription', [
                'provider_id' => $provider->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                'message' => $e->getMessage(),
            ]);
            return back()->with('error', config('app.debug')
                ? 'Could not load subscription: '.$e->getMessage()
                : 'Could not load subscription right now. Please try again.');
        }
        if (! is_string($itemId) || $itemId === '') {
            return back()->with('error', 'Unable to update subscription items.');
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
            Log::channel(config('logging.default'))->warning('ProviderSubscription changePlan failed updating stripe subscription', [
                'provider_id' => $provider->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                'plan_id' => $plan->id,
                'stripe_price_id' => $plan->stripe_price_id,
                'message' => $e->getMessage(),
            ]);
            return back()->with('error', config('app.debug')
                ? 'Could not change plan: '.$e->getMessage()
                : 'Could not change plan right now. Please try again.');
        }

        $subscription->update([
            'subscription_plan_id' => $plan->id,
        ]);

        Log::channel(config('logging.default'))->info('ProviderSubscription changePlan success (local plan id updated)', [
            'provider_id' => $provider->id,
            'subscription_id' => $subscription->id,
            'new_subscription_plan_id' => $plan->id,
        ]);

        return back()->with('success', 'Plan change scheduled successfully.');
    }

    public function cancel(Request $request, ProviderSubscription $subscription): RedirectResponse
    {
        $provider = $this->resolveProvider($request);
        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            abort(403);
        }

        if (! is_string($subscription->stripe_subscription_id) || $subscription->stripe_subscription_id === '') {
            return back()->with('error', 'No active subscription found.');
        }

        if (! StripeConfig::hasSecretKey()) {
            return back()->with('error', 'Stripe is not configured.');
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', config('app.debug')
                ? 'Could not cancel subscription: '.$e->getMessage()
                : 'Could not cancel subscription right now. Please try again.');
        }

        $subscription->update([
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
        ]);

        return back()->with('success', 'Subscription will cancel at period end.');
    }

    public function resume(Request $request, ProviderSubscription $subscription): RedirectResponse
    {
        $provider = $this->resolveProvider($request);
        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            abort(403);
        }

        if (! is_string($subscription->stripe_subscription_id) || $subscription->stripe_subscription_id === '') {
            return back()->with('error', 'No Stripe subscription found.');
        }

        if (! StripeConfig::hasSecretKey()) {
            return back()->with('error', 'Stripe is not configured.');
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', config('app.debug')
                ? 'Could not resume subscription: '.$e->getMessage()
                : 'Could not resume subscription right now. Please try again.');
        }

        $subscription->update([
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        return back()->with('success', 'Subscription resumed.');
    }

    private function resolveProvider(Request $request): ServiceProvider
    {
        $provider = $request->user()?->serviceProvider;
        abort_unless($provider, 403);

        return $provider;
    }
}
