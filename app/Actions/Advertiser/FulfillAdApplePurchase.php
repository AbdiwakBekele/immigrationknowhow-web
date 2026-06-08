<?php

namespace App\Actions\Advertiser;

use App\Models\Ad;
use App\Models\AdPayment;
use App\Services\Apple\AppStoreServerClient;
use App\Support\AppleIapConfig;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FulfillAdApplePurchase
{
    public function __construct(
        private readonly AppStoreServerClient $appStore,
    ) {}

    public function __invoke(Ad $ad, int $userId, string $transactionId): bool
    {
        if (! AppleIapConfig::configured()) {
            throw new RuntimeException('Apple In-App Purchase is not configured on the server.');
        }

        $expectedProductId = AppleIapConfig::adPublishProductId();
        if ($expectedProductId === '') {
            throw new RuntimeException('Apple ad publish product ID is not configured.');
        }

        if ((int) $ad->price_cents !== AppleIapConfig::adPublishPriceCents()) {
            throw new RuntimeException('This ad price is not available for In-App Purchase.');
        }

        $payload = $this->appStore->getTransaction($transactionId);

        $bundleId = (string) ($payload['bundleId'] ?? '');
        if ($bundleId !== AppleIapConfig::bundleId()) {
            throw new RuntimeException('Transaction bundle ID does not match this app.');
        }

        $productId = (string) ($payload['productId'] ?? '');
        if ($productId === '' || $productId !== $expectedProductId) {
            throw new RuntimeException('Transaction product does not match ad publish fee.');
        }

        if ($payload['revocationDate'] ?? null) {
            throw new RuntimeException('This purchase was revoked.');
        }

        $appleTransactionId = (string) ($payload['transactionId'] ?? $transactionId);

        if (AdPayment::query()->where('apple_transaction_id', $appleTransactionId)->exists()) {
            return $ad->status !== 'pending_payment';
        }

        return DB::transaction(function () use ($ad, $userId, $appleTransactionId, $payload): bool {
            if ($ad->isSuspended()) {
                throw new RuntimeException('This ad was suspended.');
            }

            if ($ad->status === 'published') {
                return true;
            }

            if ($ad->status !== 'pending_payment') {
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

            return true;
        });
    }
}
