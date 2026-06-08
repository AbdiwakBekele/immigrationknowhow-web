<?php

namespace App\Actions\Video;

use App\Models\VideoEmbed;
use App\Models\VideoUserAccess;
use App\Services\Apple\AppStoreServerClient;
use App\Support\AppleIapConfig;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FulfillVideoApplePurchase
{
    public function __construct(
        private readonly AppStoreServerClient $appStore,
    ) {}

    /**
     * @return bool True when access was granted or already granted.
     */
    public function __invoke(VideoEmbed $video, int $userId, string $transactionId): bool
    {
        if (! AppleIapConfig::configured()) {
            throw new RuntimeException('Apple In-App Purchase is not configured on the server.');
        }

        $payload = $this->appStore->getTransaction($transactionId);

        $bundleId = (string) ($payload['bundleId'] ?? '');
        if ($bundleId !== AppleIapConfig::bundleId()) {
            throw new RuntimeException('Transaction bundle ID does not match this app.');
        }

        $productId = (string) ($payload['productId'] ?? '');
        $expectedProductId = $video->appleProductId();
        if ($productId === '' || $productId !== $expectedProductId) {
            throw new RuntimeException('Transaction product does not match this video.');
        }

        if ($payload['revocationDate'] ?? null) {
            throw new RuntimeException('This purchase was revoked.');
        }

        $appleTransactionId = (string) ($payload['transactionId'] ?? $transactionId);
        $appleOriginalTransactionId = (string) ($payload['originalTransactionId'] ?? $appleTransactionId);

        return DB::transaction(function () use ($video, $userId, $appleTransactionId, $appleOriginalTransactionId) {
            /** @var VideoUserAccess $access */
            $access = VideoUserAccess::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'video_embed_id' => $video->id,
                ],
                []
            );

            $access->refresh();

            if ($access->purchased_at !== null) {
                return true;
            }

            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => $video->price !== null ? round((float) $video->price, 2) : null,
                'purchase_currency' => strtoupper((string) ($video->currency ?? 'USD')),
                'purchase_source' => 'apple',
                'apple_transaction_id' => $appleTransactionId,
                'apple_original_transaction_id' => $appleOriginalTransactionId,
            ]);

            return true;
        });
    }
}
