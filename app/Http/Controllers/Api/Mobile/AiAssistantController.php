<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSubscription;
use App\Services\Ai\ServiceSeekerAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Stripe\Subscription;

class AiAssistantController extends Controller
{
    public function show(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'subscription' => $subscription,
                'is_addon_active' => $subscription?->isActive() ?? false,
                'monthly_price' => '4.99',
                'currency' => 'USD',
            ],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return response()->json([
                'success' => false,
                'message' => 'AI add-on is not ready yet. Please run database migrations.',
                'errors' => (object) [],
            ], 422);
        }

        $stripeSecret = trim((string) config('services.stripe.secret', ''));
        if ($stripeSecret === '') {
            return response()->json([
                'success' => false,
                'message' => 'STRIPE_SECRET is not configured.',
                'errors' => (object) [],
            ], 422);
        }

        $priceId = trim((string) config('services.stripe.ai_assistant_price_id', ''));
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
                        'description' => 'ChatGPT assistant access for service seekers',
                    ],
                    'unit_amount' => 499,
                    'recurring' => [
                        'interval' => 'month',
                    ],
                ],
                'quantity' => 1,
            ];

        $user = $request->user();
        $successUrl = route('user.ai-assistant.index', [], true).'?checkout=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('user.ai-assistant.index', [], true).'?checkout=cancelled';

        try {
            Stripe::setApiKey($stripeSecret);
            $session = StripeCheckoutSession::create([
                'mode' => 'subscription',
                'customer_email' => $user->email,
                'client_reference_id' => (string) $user->id,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'line_items' => [$lineItem],
                'metadata' => [
                    'app' => 'ai_assistant',
                    'user_id' => (string) $user->id,
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
            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Checkout could not start: '.$e->getMessage()
                    : 'Checkout could not start. Please try again.',
                'errors' => (object) [],
            ], 422);
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Could not create checkout session.',
                'errors' => (object) [],
            ], 422);
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
                    'source' => 'checkout_created_mobile',
                ]),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'checkout_url' => $checkoutUrl,
            ],
        ]);
    }

    public function ask(Request $request, ServiceSeekerAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:6', 'max:1500'],
        ]);

        $user = $request->user();
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return response()->json([
                'success' => false,
                'message' => 'AI add-on is not ready yet. Please run database migrations.',
                'errors' => (object) [],
            ], 422);
        }

        $subscription = AiAssistantSubscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $subscription?->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Please subscribe to the AI add-on first.',
                'errors' => (object) [],
            ], 422);
        }

        $result = $assistant->ask($user, $validated['question']);
        if ($result['error']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'errors' => (object) [],
            ], 422);
        }

        if (Schema::hasTable('ai_assistant_messages')) {
            AiAssistantMessage::create([
                'user_id' => $user->id,
                'context' => 'mobile',
                'role' => 'user',
                'content' => $validated['question'],
            ]);
            AiAssistantMessage::create([
                'user_id' => $user->id,
                'context' => 'mobile',
                'role' => 'assistant',
                'content' => (string) ($result['answer'] ?? ''),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'question' => $validated['question'],
                'answer' => $result['answer'],
                'providers' => $result['providers'] ?? [],
            ],
        ]);
    }

    private function syncSubscriptionFromCheckoutSession(int $userId, string $sessionId): void
    {
        $stripeSecret = trim((string) config('services.stripe.secret', ''));
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

            $stripeSubscription = Subscription::retrieve($stripeSubscriptionId);
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
                    'source' => 'success_return_sync_mobile',
                ],
            ]
        );
    }
}
