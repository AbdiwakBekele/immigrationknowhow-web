<?php

namespace App\Actions\AiAssistant;

use App\Models\AiAssistantSubscription;
use App\Services\Apple\AppStoreServerClient;
use App\Support\AppleIapConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class FulfillAiAssistantAppleSubscription
{
    public function __construct(
        private readonly AppStoreServerClient $appStore,
    ) {}

    public function __invoke(int $userId, string $transactionId): AiAssistantSubscription
    {
        if (! AppleIapConfig::configured()) {
            throw new RuntimeException('Apple In-App Purchase is not configured on the server.');
        }

        $expectedProductId = AppleIapConfig::aiAssistantProductId();
        if ($expectedProductId === '') {
            throw new RuntimeException('Apple AI Assistant product ID is not configured.');
        }

        $payload = $this->appStore->getTransaction($transactionId);

        $bundleId = (string) ($payload['bundleId'] ?? '');
        if ($bundleId !== AppleIapConfig::bundleId()) {
            throw new RuntimeException('Transaction bundle ID does not match this app.');
        }

        $productId = (string) ($payload['productId'] ?? '');
        if ($productId !== $expectedProductId) {
            throw new RuntimeException('Transaction product does not match AI Assistant subscription.');
        }

        $appleTransactionId = (string) ($payload['transactionId'] ?? $transactionId);
        $originalTransactionId = (string) ($payload['originalTransactionId'] ?? $appleTransactionId);

        $status = 'active';
        $currentPeriodEnd = null;
        $cancelAtPeriodEnd = false;

        try {
            $subscriptionResponse = $this->appStore->getSubscriptionStatuses($originalTransactionId);
            $statusInfo = $this->resolveSubscriptionStatus($subscriptionResponse, $expectedProductId);
            $status = $statusInfo['status'];
            $currentPeriodEnd = $statusInfo['current_period_end'];
            $cancelAtPeriodEnd = $statusInfo['cancel_at_period_end'];
        } catch (RuntimeException $e) {
            Log::warning('ai_assistant.apple_subscription_status_lookup_failed', [
                'user_id' => $userId,
                'original_transaction_id' => $originalTransactionId,
                'message' => $e->getMessage(),
            ]);
        }

        if ($payload['revocationDate'] ?? null) {
            $status = 'canceled';
        }

        return AiAssistantSubscription::upsertForUser($userId, [
            'status' => $status,
            'apple_product_id' => $productId,
            'apple_transaction_id' => $appleTransactionId,
            'apple_original_transaction_id' => $originalTransactionId,
            'current_period_end' => $currentPeriodEnd,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'meta' => [
                'source' => 'apple_iap_mobile',
                'transaction' => $payload,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{status: string, current_period_end: ?Carbon, cancel_at_period_end: bool}
     */
    private function resolveSubscriptionStatus(array $response, string $expectedProductId): array
    {
        $data = $response['data'] ?? [];
        if (! is_array($data)) {
            return ['status' => 'active', 'current_period_end' => null, 'cancel_at_period_end' => false];
        }

        foreach ($data as $group) {
            if (! is_array($group)) {
                continue;
            }

            $lastTransactions = $group['lastTransactions'] ?? [];
            if (! is_array($lastTransactions)) {
                continue;
            }

            foreach ($lastTransactions as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $signedTransaction = $entry['signedTransactionInfo'] ?? null;
                if (! is_string($signedTransaction) || trim($signedTransaction) === '') {
                    continue;
                }

                $transaction = $this->appStore->decodeJwsPayload($signedTransaction);
                if ((string) ($transaction['productId'] ?? '') !== $expectedProductId) {
                    continue;
                }

                $statusCode = (int) ($entry['status'] ?? 0);
                $status = match ($statusCode) {
                    1 => 'active',
                    2 => 'expired',
                    3 => 'past_due',
                    4 => 'active',
                    5 => 'revoked',
                    default => 'active',
                };

                $expiresMs = $transaction['expiresDate'] ?? null;
                $currentPeriodEnd = is_numeric($expiresMs)
                    ? now()->setTimestamp((int) floor(((int) $expiresMs) / 1000))
                    : null;

                $signedRenewal = $entry['signedRenewalInfo'] ?? null;
                $cancelAtPeriodEnd = false;
                if (is_string($signedRenewal) && trim($signedRenewal) !== '') {
                    $renewal = $this->appStore->decodeJwsPayload($signedRenewal);
                    $cancelAtPeriodEnd = (int) ($renewal['autoRenewStatus'] ?? 1) === 0;
                }

                return [
                    'status' => $status,
                    'current_period_end' => $currentPeriodEnd,
                    'cancel_at_period_end' => $cancelAtPeriodEnd,
                ];
            }
        }

        return ['status' => 'active', 'current_period_end' => null, 'cancel_at_period_end' => false];
    }
}
