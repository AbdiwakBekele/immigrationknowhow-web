<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantSubscription;
use App\Services\Ai\ServiceSeekerAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class AiAssistantController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (
            Schema::hasTable('ai_assistant_subscriptions')
            && $request->query('checkout') === 'success'
        ) {
            $sessionIdFromQuery = is_string($request->query('session_id'))
                ? trim((string) $request->query('session_id'))
                : '';

            if ($sessionIdFromQuery !== '') {
                $this->syncSubscriptionFromCheckoutSession($user->id, $sessionIdFromQuery);
            } else {
                $lastKnownSessionId = (string) (AiAssistantSubscription::query()
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->value('stripe_checkout_session_id') ?? '');

                if ($lastKnownSessionId !== '') {
                    $this->syncSubscriptionFromCheckoutSession($user->id, $lastKnownSessionId);
                }
            }
        }

        $subscription = Schema::hasTable('ai_assistant_subscriptions')
            ? AiAssistantSubscription::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->first()
            : null;

        return Inertia::render('Provider/AiAssistant/Index', [
            'subscription' => $subscription,
            'isAddonActive' => $subscription?->isActive() ?? false,
            'monthlyPrice' => '4.99',
            'currency' => 'USD',
        ]);
    }

    public function checkout(Request $request): RedirectResponse|HttpResponse
    {
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return back()->with('error', 'AI add-on is not ready yet. Please run database migrations.');
        }

        $stripeSecret = trim((string) env('STRIPE_SECRET', ''));
        if ($stripeSecret === '') {
            return back()->with('error', 'STRIPE_SECRET is not configured.');
        }

        $priceId = trim((string) env('STRIPE_AI_ASSISTANT_PRICE_ID', ''));
        $lineItem = $priceId !== ''
            ? [
                'price' => $priceId,
                'quantity' => 1,
            ]
            : [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'AI Assistant Add-on',
                        'description' => 'Monthly AI assistant access from your provider dashboard',
                    ],
                    'unit_amount' => 499,
                    'recurring' => [
                        'interval' => 'month',
                    ],
                ],
                'quantity' => 1,
            ];

        $user = $request->user();

        try {
            Stripe::setApiKey($stripeSecret);
            $session = StripeCheckoutSession::create([
                'mode' => 'subscription',
                'customer_email' => $user->email,
                'client_reference_id' => (string) $user->id,
                'success_url' => route('provider.ai-assistant.index', [], true).'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('provider.ai-assistant.index', [], true).'?checkout=cancelled',
                'line_items' => [$lineItem],
                'metadata' => [
                    'app' => 'ai_assistant',
                    'user_id' => (string) $user->id,
                    'context' => 'provider',
                    'price_source' => $priceId !== '' ? 'price_id' : 'inline_price_data',
                ],
                'subscription_data' => [
                    'metadata' => [
                        'app' => 'ai_assistant',
                        'user_id' => (string) $user->id,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', config('app.debug')
                ? 'Checkout could not start: '.$e->getMessage()
                : 'Checkout could not start. Please try again.');
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return back()->with('error', 'Could not create checkout session.');
        }

        AiAssistantSubscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_customer_id' => is_string($session->customer) ? $session->customer : null,
                'stripe_subscription_id' => is_string($session->subscription) ? $session->subscription : null,
                'stripe_checkout_session_id' => (string) $session->id,
                'status' => 'checkout_pending',
                'meta' => array_filter([
                    'checkout_session' => $session->toArray(),
                    'price_source' => $priceId !== '' ? 'price_id' : 'inline_price_data',
                    'source' => 'checkout_created',
                    'context' => 'provider',
                ]),
            ]
        );

        return Inertia::location($checkoutUrl);
    }

    public function ask(Request $request, ServiceSeekerAssistantService $assistant): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:6', 'max:1500'],
        ]);

        $user = $request->user();
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return back()->with('error', 'AI add-on is not ready yet. Please run database migrations.');
        }

        $subscription = AiAssistantSubscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $subscription?->isActive()) {
            return back()->with('error', 'Please subscribe to the AI add-on first.');
        }

        $result = $assistant->ask($user, $validated['question'], 'provider');
        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('ai_assistant_response', [
            'question' => $validated['question'],
            'answer' => $result['answer'],
            'providers' => $result['providers'],
            'books' => $result['books'] ?? [],
        ]);
    }

    private function syncSubscriptionFromCheckoutSession(int $userId, string $sessionId): void
    {
        $stripeSecret = trim((string) env('STRIPE_SECRET', ''));
        if ($stripeSecret === '') {
            return;
        }

        try {
            Stripe::setApiKey($stripeSecret);
            $session = StripeCheckoutSession::retrieve($sessionId);
            $stripeSubscriptionId = is_string($session->subscription) ? $session->subscription : null;
            if (! $stripeSubscriptionId) {
                return;
            }

            $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
        } catch (\Throwable) {
            return;
        }

        AiAssistantSubscription::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'stripe_customer_id' => is_string($session->customer) ? $session->customer : null,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_checkout_session_id' => $sessionId,
                'status' => (string) $stripeSubscription->status,
                'cancel_at_period_end' => (bool) $stripeSubscription->cancel_at_period_end,
                'current_period_end' => is_numeric($stripeSubscription->current_period_end)
                    ? now()->setTimestamp((int) $stripeSubscription->current_period_end)
                    : null,
                'canceled_at' => is_numeric($stripeSubscription->canceled_at)
                    ? now()->setTimestamp((int) $stripeSubscription->canceled_at)
                    : null,
                'meta' => [
                    'session' => $session->toArray(),
                    'subscription' => $stripeSubscription->toArray(),
                    'source' => 'success_return_sync',
                ],
            ]
        );
    }
}
