<?php

namespace App\Support;

use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ProviderAppleSubscriptionLogger
{
    private const CHANNEL = 'provider.subscription_apple';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $step, array $context = []): void
    {
        Log::info(self::CHANNEL, array_merge(['step' => $step], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $step, array $context = []): void
    {
        Log::warning(self::CHANNEL, array_merge(['step' => $step], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $step, array $context = []): void
    {
        Log::error(self::CHANNEL, array_merge(['step' => $step], $context));
    }

    public static function logControllerRequest(Request $request, SubscriptionPlan $plan, ServiceProvider $provider): void
    {
        self::info('controller.request_received', [
            'user_id' => (int) $request->user()->id,
            'provider_id' => $provider->id,
            'plan_id' => $plan->id,
            'plan_uuid' => $plan->uuid,
            'plan_name' => $plan->name,
            'plan_billing_cycle' => $plan->billing_cycle,
            'plan_price_cents' => (int) $plan->price_cents,
            'expected_apple_product_id' => $plan->appleProductId(),
            'transaction_id' => (string) $request->input('transaction_id', ''),
            'service_types' => $provider->service_types,
            'apple_iap_configured' => AppleIapConfig::configured(),
            'client_header' => (string) $request->header('X-IKH-Client', ''),
        ]);
    }

    public static function logControllerRejected(string $reason, int $userId, ?SubscriptionPlan $plan = null, ?string $message = null): void
    {
        self::warning('controller.rejected', [
            'reason' => $reason,
            'user_id' => $userId,
            'plan_id' => $plan?->id,
            'plan_uuid' => $plan?->uuid,
            'message' => $message,
        ]);
    }

    public static function logControllerSuccess(int $userId, ProviderSubscription $subscription, SubscriptionPlan $plan): void
    {
        self::info('controller.success', [
            'user_id' => $userId,
            'provider_id' => $subscription->service_provider_id,
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid,
            'subscription_status' => $subscription->status,
            'plan_id' => $plan->id,
            'plan_uuid' => $plan->uuid,
            'apple_product_id' => $subscription->apple_product_id,
            'apple_original_transaction_id' => $subscription->apple_original_transaction_id,
        ]);
    }

    public static function logControllerFailure(int $userId, SubscriptionPlan $plan, string $transactionId, string $message): void
    {
        self::error('controller.fulfillment_failed', [
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'plan_uuid' => $plan->uuid,
            'transaction_id' => $transactionId,
            'message' => $message,
        ]);
    }

    public static function logFulfillStarted(ServiceProvider $provider, SubscriptionPlan $plan, int $userId, string $transactionId): void
    {
        self::info('fulfill.started', [
            'user_id' => $userId,
            'provider_id' => $provider->id,
            'plan_id' => $plan->id,
            'plan_uuid' => $plan->uuid,
            'plan_billing_cycle' => $plan->billing_cycle,
            'expected_apple_product_id' => $plan->appleProductId(),
            'transaction_id' => $transactionId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function logApplePayload(int $userId, string $transactionId, array $payload): void
    {
        self::info('fulfill.apple_transaction_payload', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'product_id' => $payload['productId'] ?? null,
            'bundle_id' => $payload['bundleId'] ?? null,
            'original_transaction_id' => $payload['originalTransactionId'] ?? null,
            'environment' => $payload['environment'] ?? null,
            'revocation_date' => $payload['revocationDate'] ?? null,
            'price' => $payload['price'] ?? null,
            'currency' => $payload['currency'] ?? null,
        ]);
    }

    public static function logValidationFailed(int $userId, string $reason, array $context = []): void
    {
        self::warning('fulfill.validation_failed', array_merge([
            'user_id' => $userId,
            'reason' => $reason,
        ], $context));
    }

    public static function logStatusLookupFailed(int $providerId, int $planId, string $originalTransactionId, string $message): void
    {
        self::warning('fulfill.status_lookup_failed', [
            'provider_id' => $providerId,
            'plan_id' => $planId,
            'original_transaction_id' => $originalTransactionId,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $statusInfo
     */
    public static function logStatusResolved(int $userId, array $statusInfo): void
    {
        self::info('fulfill.status_resolved', array_merge(['user_id' => $userId], $statusInfo));
    }

    public static function logFulfillSuccess(int $userId, ProviderSubscription $subscription): void
    {
        self::info('fulfill.success', [
            'user_id' => $userId,
            'provider_id' => $subscription->service_provider_id,
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid,
            'subscription_status' => $subscription->status,
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'apple_product_id' => $subscription->apple_product_id,
            'apple_original_transaction_id' => $subscription->apple_original_transaction_id,
        ]);

        AppleIapPurchaseLogger::log(
            'provider_subscription',
            $userId,
            (string) $subscription->apple_product_id,
            (string) $subscription->apple_transaction_id,
            (string) $subscription->apple_original_transaction_id,
            null,
            true,
            'Provider subscription fulfilled.',
            [
                'subscription_uuid' => $subscription->uuid,
                'subscription_status' => $subscription->status,
            ],
        );
    }

    public static function logRestoreAttempt(int $userId, int $providerId, string $productId, string $transactionId): void
    {
        self::info('restore.attempt', [
            'user_id' => $userId,
            'provider_id' => $providerId,
            'product_id' => $productId,
            'transaction_id' => $transactionId,
        ]);
    }

    public static function logRestoreResult(int $userId, int $providerId, bool $active, ?string $error = null): void
    {
        self::info('restore.result', [
            'user_id' => $userId,
            'provider_id' => $providerId,
            'provider_subscription_active' => $active,
            'error' => $error,
        ]);
    }
}
