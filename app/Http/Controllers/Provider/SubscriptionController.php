<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $provider = $this->resolveProvider($request);

        $plans = SubscriptionPlan::query()
            ->active()
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

        return Inertia::render('Provider/Subscriptions/Index', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
            'subscriptionHistory' => $history,
        ]);
    }

    public function checkout(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $provider = $this->resolveProvider($request);

        if (! in_array((string) $plan->status, ['active'], true)) {
            return back()->with('error', 'This plan is not available.');
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
                'meta' => array_merge((array) ($providerSubscription->meta ?? []), ['source' => 'free_plan_checkout']),
            ]);
            $providerSubscription->save();

            $provider->update([
                'subscription_plan' => $plan->name,
                'subscription_expires_at' => null,
                'stripe_subscription_status' => 'active',
            ]);

            return back()->with('success', 'Free plan activated.');
        }

        if (! is_string($plan->stripe_price_id) || trim($plan->stripe_price_id) === '') {
            return back()->with('error', 'Stripe price is not configured for this plan.');
        }

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || trim($secret) === '') {
            return back()->with('error', 'Stripe is not configured.');
        }

        Stripe::setApiKey($secret);

        $session = StripeCheckoutSession::create([
            'mode' => 'subscription',
            'customer_email' => $request->user()->email,
            'client_reference_id' => (string) $request->user()->id,
            'success_url' => route('provider.subscriptions.index', [], true).'?checkout=success',
            'cancel_url' => route('provider.subscriptions.index', [], true).'?checkout=cancelled',
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
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

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return back()->with('error', 'Could not start subscription checkout.');
        }

        return Inertia::location($checkoutUrl);
    }

    public function changePlan(Request $request, ProviderSubscription $subscription, SubscriptionPlan $plan): RedirectResponse
    {
        $provider = $this->resolveProvider($request);
        if ((int) $subscription->service_provider_id !== (int) $provider->id) {
            abort(403);
        }

        if (! is_string($subscription->stripe_subscription_id) || $subscription->stripe_subscription_id === '') {
            return back()->with('error', 'No Stripe subscription found.');
        }
        if (! is_string($plan->stripe_price_id) || $plan->stripe_price_id === '') {
            return back()->with('error', 'Selected plan is not configured.');
        }

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return back()->with('error', 'Stripe is not configured.');
        }

        Stripe::setApiKey($secret);
        $stripeSub = StripeSubscription::retrieve($subscription->stripe_subscription_id);
        $itemId = $stripeSub->items->data[0]->id ?? null;
        if (! is_string($itemId) || $itemId === '') {
            return back()->with('error', 'Unable to update subscription items.');
        }

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

        $subscription->update([
            'subscription_plan_id' => $plan->id,
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

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return back()->with('error', 'Stripe is not configured.');
        }

        Stripe::setApiKey($secret);
        StripeSubscription::update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

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

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return back()->with('error', 'Stripe is not configured.');
        }

        Stripe::setApiKey($secret);
        StripeSubscription::update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

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
