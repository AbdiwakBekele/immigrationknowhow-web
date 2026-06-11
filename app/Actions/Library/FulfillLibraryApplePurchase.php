<?php

namespace App\Actions\Library;

use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Services\Apple\AppStoreServerClient;
use App\Support\AppleIapConfig;
use App\Support\AppleIapPurchaseLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FulfillLibraryApplePurchase
{
    public function __construct(
        private readonly AppStoreServerClient $appStore,
    ) {}

    /**
     * @return bool True when access was granted or already granted.
     */
    public function __invoke(LibraryItem $item, int $userId, string $transactionId): bool
    {
        if (! AppleIapConfig::configured()) {
            throw new RuntimeException('Apple In-App Purchase is not configured on the server.');
        }

        $expectedProductId = $item->appleProductId();

        try {
            $payload = $this->appStore->getTransaction($transactionId);
        } catch (RuntimeException $e) {
            AppleIapPurchaseLogger::log(
                purchaseType: 'ebook',
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

        $bundleId = (string) ($payload['bundleId'] ?? '');
        if ($bundleId !== AppleIapConfig::bundleId()) {
            throw new RuntimeException('Transaction bundle ID does not match this app.');
        }

        $productId = (string) ($payload['productId'] ?? '');
        if ($productId === '' || $productId !== $expectedProductId) {
            throw new RuntimeException('Transaction product does not match this library item.');
        }

        $revocationDate = $payload['revocationDate'] ?? null;
        if ($revocationDate !== null) {
            throw new RuntimeException('This purchase was revoked.');
        }

        $appleTransactionId = (string) ($payload['transactionId'] ?? $transactionId);
        $appleOriginalTransactionId = (string) ($payload['originalTransactionId'] ?? $appleTransactionId);
        $environment = (string) ($payload['_apple_environment'] ?? $this->appStore->lastSuccessfulEnvironment() ?? 'unknown');

        $existingByTransaction = LibraryUserAccess::query()
            ->where('user_id', $userId)
            ->where('library_item_id', $item->id)
            ->where('apple_transaction_id', $appleTransactionId)
            ->whereNotNull('purchased_at')
            ->exists();

        if ($existingByTransaction) {
            AppleIapPurchaseLogger::log(
                purchaseType: 'ebook',
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

        $granted = DB::transaction(function () use ($item, $userId, $appleTransactionId, $appleOriginalTransactionId) {
            /** @var LibraryUserAccess $access */
            $access = LibraryUserAccess::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'library_item_id' => $item->id,
                ],
                []
            );

            $access->refresh();

            if ($access->purchased_at !== null) {
                return true;
            }

            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => $item->price !== null ? round((float) $item->price, 2) : null,
                'purchase_currency' => strtoupper((string) ($item->currency ?? 'USD')),
                'purchase_source' => 'apple',
                'apple_transaction_id' => $appleTransactionId,
                'apple_original_transaction_id' => $appleOriginalTransactionId,
            ]);

            return true;
        });

        AppleIapPurchaseLogger::log(
            purchaseType: 'ebook',
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
