<?php

namespace App\Support;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class AdApplePurchaseLogger
{
    private const CHANNEL = 'ads.apple_purchase';

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

    public static function logControllerRequest(Request $request, Ad $ad, int $userId): void
    {
        self::info('controller.request_received', [
            'user_id' => $userId,
            'ad_id' => $ad->id,
            'ad_uuid' => $ad->uuid,
            'ad_title' => $ad->title,
            'ad_status' => $ad->status,
            'ad_price_cents' => (int) $ad->price_cents,
            'ad_currency' => $ad->currency,
            'expected_price_cents' => AppleIapConfig::adPublishPriceCents(),
            'expected_apple_product_id' => AppleIapConfig::adPublishProductId(),
            'resolved_apple_product_id' => $ad->applePublishProductId(),
            'transaction_id' => (string) $request->input('transaction_id', ''),
            'client_product_id' => (string) $request->input('product_id', ''),
            'client_original_transaction_id' => (string) $request->input('original_transaction_id', ''),
            'apple_iap_configured' => AppleIapConfig::configured(),
            'client_header' => (string) $request->header('X-IKH-Client', ''),
        ]);
    }

    public static function logControllerRejected(string $reason, int $userId, Ad $ad, ?string $message = null): void
    {
        self::warning('controller.rejected', [
            'reason' => $reason,
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'ad_status' => $ad->status,
            'message' => $message,
        ]);
    }

    public static function logPriceNormalized(int $userId, Ad $ad, int $previousPriceCents): void
    {
        self::info('controller.price_normalized', [
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'previous_price_cents' => $previousPriceCents,
            'new_price_cents' => (int) $ad->price_cents,
        ]);
    }

    public static function logControllerSuccess(int $userId, Ad $ad, bool $fulfilled): void
    {
        self::info('controller.success', [
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'ad_status' => $ad->fresh()?->status,
            'fulfilled' => $fulfilled,
        ]);
    }

    public static function logControllerFailure(int $userId, Ad $ad, string $transactionId, string $message): void
    {
        self::error('controller.fulfillment_failed', [
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'transaction_id' => $transactionId,
            'message' => $message,
        ]);
    }

    public static function logFulfillStarted(Ad $ad, int $userId, string $transactionId): void
    {
        self::info('fulfill.started', [
            'user_id' => $userId,
            'ad_id' => $ad->id,
            'ad_uuid' => $ad->uuid,
            'ad_status' => $ad->status,
            'ad_price_cents' => (int) $ad->price_cents,
            'transaction_id' => $transactionId,
            'expected_product_id' => AppleIapConfig::adPublishProductId(),
            'expected_price_cents' => AppleIapConfig::adPublishPriceCents(),
        ]);
    }

    public static function logPrecheckFailed(int $userId, Ad $ad, string $reason, array $extra = []): void
    {
        self::warning('fulfill.precheck_failed', array_merge([
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'ad_status' => $ad->status,
            'ad_price_cents' => (int) $ad->price_cents,
            'reason' => $reason,
        ], $extra));
    }

    public static function logAppleFetchStarted(int $userId, string $transactionId): void
    {
        self::info('fulfill.apple_fetch_started', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'sandbox_mode' => AppleIapConfig::useSandbox(),
        ]);
    }

    public static function logAppleFetchFailed(int $userId, string $transactionId, string $message, ?string $environment): void
    {
        self::error('fulfill.apple_fetch_failed', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'message' => $message,
            'environment' => $environment,
            'sandbox_mode' => AppleIapConfig::useSandbox(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function logApplePayload(int $userId, string $transactionId, array $payload): void
    {
        self::info('fulfill.apple_payload_received', [
            'user_id' => $userId,
            'requested_transaction_id' => $transactionId,
            'product_id' => (string) ($payload['productId'] ?? ''),
            'transaction_id' => (string) ($payload['transactionId'] ?? ''),
            'original_transaction_id' => (string) ($payload['originalTransactionId'] ?? ''),
            'bundle_id' => (string) ($payload['bundleId'] ?? ''),
            'type' => (string) ($payload['type'] ?? ''),
            'environment' => (string) ($payload['_apple_environment'] ?? ''),
            'purchase_date_ms' => $payload['purchaseDate'] ?? null,
            'revocation_date_ms' => $payload['revocationDate'] ?? null,
        ]);
    }

    public static function logValidationFailed(int $userId, Ad $ad, string $reason, array $context = []): void
    {
        self::warning('fulfill.validation_failed', array_merge([
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'reason' => $reason,
        ], $context));
    }

    public static function logDuplicateTransaction(int $userId, Ad $ad, string $appleTransactionId): void
    {
        self::info('fulfill.duplicate_transaction', [
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'apple_transaction_id' => $appleTransactionId,
        ]);
    }

    public static function logPaymentRecorded(int $userId, Ad $ad, string $appleTransactionId, string $newStatus): void
    {
        self::info('fulfill.payment_recorded', [
            'user_id' => $userId,
            'ad_uuid' => $ad->uuid,
            'apple_transaction_id' => $appleTransactionId,
            'ad_status' => $newStatus,
            'amount_cents' => (int) $ad->price_cents,
            'currency' => $ad->currency,
        ]);
    }
}
