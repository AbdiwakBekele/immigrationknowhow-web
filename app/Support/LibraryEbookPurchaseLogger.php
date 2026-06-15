<?php

namespace App\Support;

use App\Models\LibraryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class LibraryEbookPurchaseLogger
{
    private const CHANNEL = 'library.ebook_apple_purchase';

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

    public static function logControllerRequest(Request $request, LibraryItem $item, int $userId): void
    {
        self::info('controller.request_received', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'library_title' => $item->title,
            'item_price' => $item->price,
            'item_currency' => $item->currency,
            'item_type' => $item->type,
            'qualifies_for_ebook_credit' => LibraryEbookPricing::itemQualifiesForEbookCredit($item),
            'expected_apple_product_id' => AppleIapConfig::libraryEbookCreditProductId(),
            'transaction_id' => (string) $request->input('transaction_id', ''),
            'client_product_id' => (string) $request->input('product_id', ''),
            'client_original_transaction_id' => (string) $request->input('original_transaction_id', ''),
            'apple_iap_configured' => AppleIapConfig::configured(),
            'client_header' => (string) $request->header('X-IKH-Client', ''),
        ]);
    }

    public static function logControllerRejected(string $reason, int $userId, LibraryItem $item, ?string $message = null): void
    {
        self::warning('controller.rejected', [
            'reason' => $reason,
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'message' => $message,
        ]);
    }

    public static function logControllerSuccess(int $userId, LibraryItem $item, bool $fulfilled): void
    {
        self::info('controller.success', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'fulfilled' => $fulfilled,
        ]);
    }

    public static function logControllerFailure(int $userId, LibraryItem $item, string $transactionId, string $message): void
    {
        self::error('controller.fulfillment_failed', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'transaction_id' => $transactionId,
            'message' => $message,
        ]);
    }

    public static function logFulfillStarted(LibraryItem $item, int $userId, string $transactionId): void
    {
        self::info('fulfill.started', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'transaction_id' => $transactionId,
            'expected_product_id' => AppleIapConfig::libraryEbookCreditProductId(),
            'standard_price_cents' => LibraryEbookPricing::standardPriceCents(),
        ]);
    }

    public static function logPrecheckFailed(int $userId, LibraryItem $item, string $reason): void
    {
        self::warning('fulfill.precheck_failed', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'reason' => $reason,
        ]);
    }

    public static function logAppleFetchStarted(int $userId, string $transactionId): void
    {
        self::info('fulfill.apple_fetch_started', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'sandbox' => AppleIapConfig::useSandbox(),
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

    public static function logValidationFailed(int $userId, LibraryItem $item, string $reason, array $context = []): void
    {
        self::warning('fulfill.validation_failed', array_merge([
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'reason' => $reason,
        ], $context));
    }

    public static function logDuplicateTransaction(int $userId, LibraryItem $item, string $appleTransactionId): void
    {
        self::info('fulfill.duplicate_transaction', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'apple_transaction_id' => $appleTransactionId,
        ]);
    }

    public static function logAccessGranted(int $userId, LibraryItem $item, int $accessId, string $appleTransactionId): void
    {
        self::info('fulfill.access_granted', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'library_user_access_id' => $accessId,
            'apple_transaction_id' => $appleTransactionId,
            'purchase_source' => 'apple',
        ]);
    }

    public static function logAccessNotGranted(int $userId, LibraryItem $item, string $appleTransactionId): void
    {
        self::error('fulfill.access_not_granted', [
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_slug' => $item->slug,
            'apple_transaction_id' => $appleTransactionId,
        ]);
    }
}
