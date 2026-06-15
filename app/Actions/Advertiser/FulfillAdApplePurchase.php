<?php

namespace App\Actions\Advertiser;

use App\Models\Ad;
use App\Models\AdPayment;
use App\Services\Apple\AppStoreServerClient;
use App\Support\AdApplePurchaseLogger;
use App\Support\AppleIapConfig;
use App\Support\AppleIapPurchaseLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FulfillAdApplePurchase
{
    public function __construct(
        private readonly AppStoreServerClient $appStore,
    ) {}

    public function __invoke(Ad $ad, int $userId, string $transactionId): bool
    {
        AdApplePurchaseLogger::logFulfillStarted($ad, $userId, $transactionId);

        if (! AppleIapConfig::configured()) {
            AdApplePurchaseLogger::logPrecheckFailed($userId, $ad, 'apple_iap_not_configured');
            throw new RuntimeException('Apple In-App Purchase is not configured on the server.');
        }

        $expectedProductId = AppleIapConfig::adPublishProductId();
        if ($expectedProductId === '') {
            AdApplePurchaseLogger::logPrecheckFailed($userId, $ad, 'ad_publish_product_id_missing');
            throw new RuntimeException('Apple ad publish product ID is not configured.');
        }

        $expectedPriceCents = AppleIapConfig::adPublishPriceCents();
        if ((int) $ad->price_cents !== $expectedPriceCents) {
            AdApplePurchaseLogger::logPrecheckFailed($userId, $ad, 'ad_price_mismatch', [
                'expected_price_cents' => $expectedPriceCents,
                'actual_price_cents' => (int) $ad->price_cents,
            ]);
            throw new RuntimeException('This ad price is not available for In-App Purchase.');
        }

        AdApplePurchaseLogger::logAppleFetchStarted($userId, $transactionId);

        try {
            $payload = $this->appStore->getTransaction($transactionId);
        } catch (RuntimeException $e) {
            AdApplePurchaseLogger::logAppleFetchFailed(
                $userId,
                $transactionId,
                $e->getMessage(),
                $this->appStore->lastSuccessfulEnvironment(),
            );

            AppleIapPurchaseLogger::log(
                purchaseType: 'ad_publish',
                userId: $userId,
                productId: $expectedProductId,
                transactionId: $transactionId,
                originalTransactionId: null,
                environment: $this->appStore->lastSuccessfulEnvironment(),
                success: false,
                message: $e->getMessage(),
                extra: ['ad_id' => $ad->id, 'ad_uuid' => $ad->uuid],
            );

            throw $e;
        }

        AdApplePurchaseLogger::logApplePayload($userId, $transactionId, $payload);

        $bundleId = (string) ($payload['bundleId'] ?? '');
        if ($bundleId !== AppleIapConfig::bundleId()) {
            AdApplePurchaseLogger::logValidationFailed($userId, $ad, 'bundle_id_mismatch', [
                'expected_bundle_id' => AppleIapConfig::bundleId(),
                'received_bundle_id' => $bundleId,
            ]);
            throw new RuntimeException('Transaction bundle ID does not match this app.');
        }

        $productId = (string) ($payload['productId'] ?? '');
        if ($productId === '' || $productId !== $expectedProductId) {
            AdApplePurchaseLogger::logValidationFailed($userId, $ad, 'product_id_mismatch', [
                'expected_product_id' => $expectedProductId,
                'received_product_id' => $productId,
            ]);
            throw new RuntimeException('Transaction product does not match ad publish fee.');
        }

        if ($payload['revocationDate'] ?? null) {
            AdApplePurchaseLogger::logValidationFailed($userId, $ad, 'transaction_revoked', [
                'revocation_date_ms' => $payload['revocationDate'],
            ]);
            throw new RuntimeException('This purchase was revoked.');
        }

        $appleTransactionId = (string) ($payload['transactionId'] ?? $transactionId);
        $appleOriginalTransactionId = (string) ($payload['originalTransactionId'] ?? $appleTransactionId);
        $environment = (string) ($payload['_apple_environment'] ?? $this->appStore->lastSuccessfulEnvironment() ?? 'unknown');

        if (AdPayment::query()->where('apple_transaction_id', $appleTransactionId)->exists()) {
            AdApplePurchaseLogger::logDuplicateTransaction($userId, $ad, $appleTransactionId);

            AppleIapPurchaseLogger::log(
                purchaseType: 'ad_publish',
                userId: $userId,
                productId: $productId,
                transactionId: $appleTransactionId,
                originalTransactionId: $appleOriginalTransactionId,
                environment: $environment,
                success: true,
                message: 'idempotent_duplicate_transaction',
                extra: ['ad_id' => $ad->id, 'ad_uuid' => $ad->uuid],
            );

            return $ad->status !== 'pending_payment';
        }

        $fulfilled = DB::transaction(function () use ($ad, $userId, $appleTransactionId, $payload): bool {
            if ($ad->isSuspended()) {
                AdApplePurchaseLogger::logPrecheckFailed($userId, $ad, 'ad_suspended');
                throw new RuntimeException('This ad was suspended.');
            }

            if ($ad->status === 'published') {
                return true;
            }

            if ($ad->status !== 'pending_payment') {
                AdApplePurchaseLogger::logPrecheckFailed($userId, $ad, 'checkout_not_available', [
                    'ad_status' => $ad->status,
                ]);
                throw new RuntimeException('Checkout is not available for this ad.');
            }

            AdPayment::query()->updateOrCreate(
                ['apple_transaction_id' => $appleTransactionId],
                [
                    'ad_id' => $ad->id,
                    'user_id' => $userId,
                    'amount_cents' => max(0, (int) $ad->price_cents),
                    'currency' => strtoupper((string) ($ad->currency ?? config('ads.currency', 'USD'))),
                    'status' => 'paid',
                    'purchase_source' => 'apple',
                    'paid_at' => now(),
                    'meta' => [
                        'source' => 'apple_iap_mobile',
                        'transaction' => $payload,
                    ],
                ],
            );

            $requireApproval = (bool) config('ads.require_admin_approval', true);

            if ($requireApproval) {
                $ad->update([
                    'status' => 'pending_approval',
                    'paid_at' => $ad->paid_at ?? now(),
                    'published_at' => null,
                ]);
            } else {
                $ad->update([
                    'status' => 'published',
                    'paid_at' => $ad->paid_at ?? now(),
                    'published_at' => $ad->published_at ?? now(),
                ]);
            }

            AdApplePurchaseLogger::logPaymentRecorded($userId, $ad->fresh(), $appleTransactionId, (string) $ad->fresh()?->status);

            return true;
        });

        AppleIapPurchaseLogger::log(
            purchaseType: 'ad_publish',
            userId: $userId,
            productId: $productId,
            transactionId: $appleTransactionId,
            originalTransactionId: $appleOriginalTransactionId,
            environment: $environment,
            success: $fulfilled,
            extra: ['ad_id' => $ad->id, 'ad_uuid' => $ad->uuid],
        );

        return $fulfilled;
    }
}
