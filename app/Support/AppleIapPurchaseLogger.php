<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class AppleIapPurchaseLogger
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function log(
        string $purchaseType,
        int $userId,
        string $productId,
        string $transactionId,
        ?string $originalTransactionId,
        ?string $environment,
        bool $success,
        ?string $message = null,
        array $extra = [],
    ): void {
        Log::info('apple_iap.validation', array_merge([
            'purchase_type' => $purchaseType,
            'user_id' => $userId,
            'product_id' => $productId,
            'transaction_id' => $transactionId,
            'original_transaction_id' => $originalTransactionId,
            'environment' => $environment,
            'validation_result' => $success ? 'success' : 'failure',
            'message' => $message,
        ], $extra));
    }

    public static function logNotConfigured(string $endpoint, int $userId): void
    {
        Log::error('apple_iap.not_configured', [
            'endpoint' => $endpoint,
            'user_id' => $userId,
            'bundle_id' => AppleIapConfig::bundleId(),
            'has_issuer_id' => AppleIapConfig::issuerId() !== '',
            'has_key_id' => AppleIapConfig::keyId() !== '',
            'has_private_key' => AppleIapConfig::privateKey() !== '',
        ]);
    }
}
