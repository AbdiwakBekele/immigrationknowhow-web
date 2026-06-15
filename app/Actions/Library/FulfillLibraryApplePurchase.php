<?php

namespace App\Actions\Library;

use App\Models\LibraryItem;
use App\Services\Apple\AppStoreServerClient;
use App\Support\AppleIapConfig;
use App\Support\AppleIapPurchaseLogger;
use App\Support\LibraryEbookPricing;
use App\Support\LibraryEbookPurchaseLogger;
use RuntimeException;

final class FulfillLibraryApplePurchase
{
    public function __construct(
        private readonly AppStoreServerClient $appStore,
        private readonly GrantLibraryItemAccess $grantAccess,
    ) {}

    /**
     * Redeem one consumable ebook credit for the given library item.
     *
     * @return bool True when access was granted or already granted.
     */
    public function __invoke(LibraryItem $item, int $userId, string $transactionId): bool
    {
        LibraryEbookPurchaseLogger::logFulfillStarted($item, $userId, $transactionId);

        if (! AppleIapConfig::configured()) {
            LibraryEbookPurchaseLogger::logPrecheckFailed($userId, $item, 'apple_iap_not_configured');
            throw new RuntimeException('Apple In-App Purchase is not configured on the server.');
        }

        if (! LibraryEbookPricing::itemQualifiesForEbookCredit($item)) {
            LibraryEbookPurchaseLogger::logPrecheckFailed($userId, $item, 'item_not_eligible_for_ebook_credit');
            throw new RuntimeException('This title is not available for ebook credit purchase on iOS.');
        }

        $expectedProductId = AppleIapConfig::libraryEbookCreditProductId();
        if ($expectedProductId === '') {
            LibraryEbookPurchaseLogger::logPrecheckFailed($userId, $item, 'ebook_credit_product_id_missing');
            throw new RuntimeException('Apple ebook credit product ID is not configured.');
        }

        LibraryEbookPurchaseLogger::logAppleFetchStarted($userId, $transactionId);

        try {
            $payload = $this->appStore->getTransaction($transactionId);
        } catch (RuntimeException $e) {
            LibraryEbookPurchaseLogger::logAppleFetchFailed(
                $userId,
                $transactionId,
                $e->getMessage(),
                $this->appStore->lastSuccessfulEnvironment(),
            );

            AppleIapPurchaseLogger::log(
                purchaseType: 'ebook_credit',
                userId: $userId,
                productId: $expectedProductId,
                transactionId: $transactionId,
                originalTransactionId: null,
                environment: $this->appStore->lastSuccessfulEnvironment(),
                success: false,
                message: $e->getMessage(),
                extra: ['library_item_id' => $item->id, 'library_slug' => $item->slug],
            );

            throw $e;
        }

        LibraryEbookPurchaseLogger::logApplePayload($userId, $transactionId, $payload);

        $bundleId = (string) ($payload['bundleId'] ?? '');
        if ($bundleId !== AppleIapConfig::bundleId()) {
            LibraryEbookPurchaseLogger::logValidationFailed($userId, $item, 'bundle_id_mismatch', [
                'expected_bundle_id' => AppleIapConfig::bundleId(),
                'received_bundle_id' => $bundleId,
            ]);
            throw new RuntimeException('Transaction bundle ID does not match this app.');
        }

        $productId = (string) ($payload['productId'] ?? '');
        if ($productId === '' || $productId !== $expectedProductId) {
            LibraryEbookPurchaseLogger::logValidationFailed($userId, $item, 'product_id_mismatch', [
                'expected_product_id' => $expectedProductId,
                'received_product_id' => $productId,
            ]);
            throw new RuntimeException('Transaction product does not match ebook credit.');
        }

        if ($payload['revocationDate'] ?? null) {
            LibraryEbookPurchaseLogger::logValidationFailed($userId, $item, 'transaction_revoked', [
                'revocation_date_ms' => $payload['revocationDate'],
            ]);
            throw new RuntimeException('This purchase was revoked.');
        }

        $appleTransactionId = (string) ($payload['transactionId'] ?? $transactionId);
        $appleOriginalTransactionId = (string) ($payload['originalTransactionId'] ?? $appleTransactionId);
        $environment = (string) ($payload['_apple_environment'] ?? $this->appStore->lastSuccessfulEnvironment() ?? 'unknown');

        $existingByTransaction = \App\Models\LibraryUserAccess::query()
            ->where('user_id', $userId)
            ->where('library_item_id', $item->id)
            ->where('apple_transaction_id', $appleTransactionId)
            ->whereNotNull('purchased_at')
            ->exists();

        if ($existingByTransaction) {
            LibraryEbookPurchaseLogger::logDuplicateTransaction($userId, $item, $appleTransactionId);

            AppleIapPurchaseLogger::log(
                purchaseType: 'ebook_credit',
                userId: $userId,
                productId: $productId,
                transactionId: $appleTransactionId,
                originalTransactionId: $appleOriginalTransactionId,
                environment: $environment,
                success: true,
                message: 'idempotent_duplicate_transaction',
                extra: ['library_item_id' => $item->id, 'library_slug' => $item->slug],
            );

            return true;
        }

        $access = ($this->grantAccess)($item, $userId, [
            'purchase_amount' => LibraryEbookPricing::standardPriceAmount(),
            'purchase_currency' => LibraryEbookPricing::currency(),
            'purchase_source' => 'apple',
            'apple_transaction_id' => $appleTransactionId,
            'apple_original_transaction_id' => $appleOriginalTransactionId,
        ]);

        $granted = $access->purchased_at !== null;

        if ($granted) {
            LibraryEbookPurchaseLogger::logAccessGranted($userId, $item, (int) $access->id, $appleTransactionId);
        } else {
            LibraryEbookPurchaseLogger::logAccessNotGranted($userId, $item, $appleTransactionId);
        }

        AppleIapPurchaseLogger::log(
            purchaseType: 'ebook_credit',
            userId: $userId,
            productId: $productId,
            transactionId: $appleTransactionId,
            originalTransactionId: $appleOriginalTransactionId,
            environment: $environment,
            success: $granted,
            extra: ['library_item_id' => $item->id, 'library_slug' => $item->slug],
        );

        return $granted;
    }
}
