<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSubscription;
use App\Services\Ai\ServiceSeekerAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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
                try {
                    $this->syncSubscriptionFromCheckoutSession($user->id, $sessionIdFromQuery);
                } catch (RuntimeException) {
                    // Best-effort when called via legacy query sync.
                }
            } else {
                $lastKnownSessionId = (string) (AiAssistantSubscription::query()
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->value('stripe_checkout_session_id') ?? '');

                if ($lastKnownSessionId !== '') {
                    try {
                        $this->syncSubscriptionFromCheckoutSession($user->id, $lastKnownSessionId);
                    } catch (RuntimeException) {
                        // Best-effort.
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $this->buildStatePayload($user->id),
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
        $successUrl = route('mobile.ai-assistant.checkout-return', [], true).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('mobile.ai-assistant.checkout-return', [], true).'?checkout=cancelled';

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
                'checkout_session_id' => (string) $session->id,
            ],
        ]);
    }

    public function confirmCheckout(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return response()->json([
                'success' => false,
                'message' => 'AI add-on is not ready yet. Please run database migrations.',
                'errors' => (object) [],
            ], 422);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        $sessionId = trim((string) $validated['session_id']);
        $userId = (int) $request->user()->id;

        try {
            $subscription = $this->syncSubscriptionFromCheckoutSession($userId, $sessionId);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 422);
        }

        if (! $subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment is still processing. Please wait a moment and try again.',
                'errors' => (object) [],
            ], 422);
        }

        $payload = $this->buildStatePayload($userId);

        return response()->json([
            'success' => true,
            'message' => 'AI Assistant subscription is active.',
            'data' => $payload,
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

    /**
     * @return array<string, mixed>
     */
    private function buildStatePayload(int $userId): array
    {
        $subscription = Schema::hasTable('ai_assistant_subscriptions')
            ? AiAssistantSubscription::query()
                ->where('user_id', $userId)
                ->latest('id')
                ->first()
            : null;

        $chatMessages = Schema::hasTable('ai_assistant_messages')
            ? AiAssistantMessage::query()
                ->where('user_id', $userId)
                ->where('context', 'mobile')
                ->orderBy('id')
                ->limit(60)
                ->get(['id', 'role', 'content', 'created_at'])
                ->map(fn ($m) => [
                    'id' => (string) $m->id,
                    'role' => $m->role,
                    'text' => $m->content,
                    'ts' => optional($m->created_at)->toISOString(),
                ])
                ->values()
            : collect();

        return [
            'subscription' => $subscription,
            'is_addon_active' => $subscription?->isActive() ?? false,
            'monthly_price' => '4.99',
            'currency' => 'USD',
            'chat_messages' => $chatMessages,
        ];
    }

    private function syncSubscriptionFromCheckoutSession(int $userId, string $sessionId): AiAssistantSubscription
    {
        $stripeSecret = trim((string) config('services.stripe.secret', ''));
        if ($stripeSecret === '') {
            throw new RuntimeException('STRIPE_SECRET is not configured.');
        }

        Stripe::setApiKey($stripeSecret);

        $session = null;
        $stripeSubscription = null;
        $stripeSubscriptionId = null;
        $lastError = null;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                $session = StripeCheckoutSession::retrieve($sessionId, [
                    'expand' => ['subscription'],
                ]);
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt < 9) {
                    usleep(400_000);

                    continue;
                }

                throw new RuntimeException(
                    config('app.debug')
                        ? 'Could not verify checkout session: '.$e->getMessage()
                        : 'Could not verify checkout session.',
                    0,
                    $e
                );
            }

            $metadataUserId = (int) ($session->metadata['user_id'] ?? 0);
            if ($metadataUserId > 0 && $metadataUserId !== $userId) {
                throw new RuntimeException('Session does not belong to this user.');
            }

            $app = (string) ($session->metadata['app'] ?? '');
            if ($app !== '' && $app !== 'ai_assistant') {
                throw new RuntimeException('Invalid checkout session type.');
            }

            if ($session->status !== 'complete') {
                if ($attempt < 9) {
                    usleep(400_000);

                    continue;
                }

                throw new RuntimeException('Checkout is not complete yet.');
            }

            if (is_string($session->subscription) && $session->subscription !== '') {
                $stripeSubscriptionId = $session->subscription;
            } elseif (is_object($session->subscription) && isset($session->subscription->id)) {
                $stripeSubscriptionId = (string) $session->subscription->id;
                $stripeSubscription = $session->subscription;
            }

            if (! $stripeSubscriptionId) {
                if ($attempt < 9) {
                    usleep(400_000);

                    continue;
                }

                throw new RuntimeException('Subscription not found on checkout session.');
            }

            if (! $stripeSubscription) {
                try {
                    $stripeSubscription = Subscription::retrieve($stripeSubscriptionId);
                } catch (\Throwable $e) {
                    $lastError = $e;
                    if ($attempt < 9) {
                        usleep(400_000);

                        continue;
                    }

                    throw new RuntimeException(
                        config('app.debug')
                            ? 'Could not load subscription: '.$e->getMessage()
                            : 'Could not load subscription.',
                        0,
                        $e
                    );
                }
            }

            $status = (string) $stripeSubscription->status;
            if (in_array($status, ['active', 'trialing', 'past_due'], true)) {
                break;
            }

            if ($attempt < 9) {
                usleep(400_000);
                $stripeSubscription = null;

                continue;
            }

            throw new RuntimeException('Subscription is not active yet (status: '.$status.').');
        }

        if (! $session instanceof StripeCheckoutSession || ! $stripeSubscriptionId || ! $stripeSubscription) {
            throw new RuntimeException(
                config('app.debug') && $lastError instanceof \Throwable
                    ? 'Could not confirm subscription: '.$lastError->getMessage()
                    : 'Could not confirm subscription.'
            );
        }

        return AiAssistantSubscription::query()->updateOrCreate(
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
                    'source' => 'confirm_checkout_mobile',
                ],
            ]
        );
    }
}
