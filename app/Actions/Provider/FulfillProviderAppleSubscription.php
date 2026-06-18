<?php

namespace App\Actions\Provider;

use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Services\Apple\AppStoreServerClient;
use App\Support\AppleIapConfig;
use App\Support\ProviderAppleSubscriptionLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FulfillProviderAppleSubscription
{
    public function __construct(
        private readonly AppStoreServerClient $appStore,
    ) {}

    public function __invoke(ServiceProvider $provider, SubscriptionPlan $plan, int $userId, string $transactionId): ProviderSubscription
    {
        ProviderAppleSubscriptionLogger::logFulfillStarted($provider, $plan, $userId, $transactionId);

        if (! AppleIapConfig::configured()) {
            ProviderAppleSubscriptionLogger::logValidationFailed($userId, 'apple_iap_not_configured', [
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
            ]);
            throw new RuntimeException('Apple In-App Purchase is not configured on the server.');
        }

        $expectedProductId = $plan->appleProductId();
        $appleStartedAt = microtime(true);
        ProviderAppleSubscriptionLogger::info('fulfill.apple_get_transaction.start', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
        ]);
        $payload = $this->appStore->getTransaction($transactionId);
        ProviderAppleSubscriptionLogger::info('fulfill.apple_get_transaction.done', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'elapsed_ms' => (int) round((microtime(true) - $appleStartedAt) * 1000),
            'apple_environment' => $payload['_apple_environment'] ?? null,
        ]);
        ProviderAppleSubscriptionLogger::logApplePayload($userId, $transactionId, $payload);

        $bundleId = (string) ($payload['bundleId'] ?? '');
        if ($bundleId !== AppleIapConfig::bundleId()) {
            ProviderAppleSubscriptionLogger::logValidationFailed($userId, 'bundle_id_mismatch', [
                'expected_bundle_id' => AppleIapConfig::bundleId(),
                'actual_bundle_id' => $bundleId,
            ]);
            throw new RuntimeException('Transaction bundle ID does not match this app.');
        }

        $productId = (string) ($payload['productId'] ?? '');
        if ($productId === '' || $productId !== $expectedProductId) {
            ProviderAppleSubscriptionLogger::logValidationFailed($userId, 'product_id_mismatch', [
                'expected_product_id' => $expectedProductId,
                'actual_product_id' => $productId,
            ]);
            throw new RuntimeException('Transaction product does not match this subscription plan.');
        }

        if ($payload['revocationDate'] ?? null) {
            ProviderAppleSubscriptionLogger::logValidationFailed($userId, 'transaction_revoked', [
                'revocation_date' => $payload['revocationDate'],
            ]);
            throw new RuntimeException('This purchase was revoked.');
        }

        $appleTransactionId = (string) ($payload['transactionId'] ?? $transactionId);
        $originalTransactionId = (string) ($payload['originalTransactionId'] ?? $appleTransactionId);

        $status = 'active';
        $currentPeriodEnd = $this->defaultPeriodEnd($plan);
        $cancelAtPeriodEnd = false;

        try {
            $statusStartedAt = microtime(true);
            ProviderAppleSubscriptionLogger::info('fulfill.apple_get_subscription_status.start', [
                'user_id' => $userId,
                'original_transaction_id' => $originalTransactionId,
            ]);
            $subscriptionResponse = $this->appStore->getSubscriptionStatuses($originalTransactionId);
            ProviderAppleSubscriptionLogger::info('fulfill.apple_get_subscription_status.done', [
                'user_id' => $userId,
                'original_transaction_id' => $originalTransactionId,
                'elapsed_ms' => (int) round((microtime(true) - $statusStartedAt) * 1000),
                'apple_environment' => $this->appStore->lastSuccessfulEnvironment(),
            ]);
            $statusInfo = $this->resolveSubscriptionStatus($subscriptionResponse, $expectedProductId);
            $status = $statusInfo['status'];
            $currentPeriodEnd = $statusInfo['current_period_end'] ?? $currentPeriodEnd;
            $cancelAtPeriodEnd = $statusInfo['cancel_at_period_end'];
            ProviderAppleSubscriptionLogger::logStatusResolved($userId, [
                'status' => $status,
                'current_period_end' => $currentPeriodEnd?->toIso8601String(),
                'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);
        } catch (RuntimeException $e) {
            ProviderAppleSubscriptionLogger::logStatusLookupFailed(
                $provider->id,
                $plan->id,
                $originalTransactionId,
                $e->getMessage(),
            );
        }

        $record = DB::transaction(function () use (
            $provider,
            $plan,
            $userId,
            $status,
            $currentPeriodEnd,
            $cancelAtPeriodEnd,
            $productId,
            $appleTransactionId,
            $originalTransactionId,
            $payload,
        ) {
            $placeholder = ProviderSubscription::query()
                ->where('service_provider_id', $provider->id)
                ->where('subscription_plan_id', $plan->id)
                ->whereNull('stripe_subscription_id')
                ->whereIn('status', ['incomplete', 'active', 'trialing', 'past_due'])
                ->latest('id')
                ->first();

            $record = $placeholder ?? ProviderSubscription::query()->firstOrNew([
                'service_provider_id' => $provider->id,
                'subscription_plan_id' => $plan->id,
            ]);

            $record->fill([
                'status' => in_array($status, ['active', 'trialing', 'past_due'], true) ? $status : 'active',
                'started_at' => $record->started_at ?? now(),
                'current_period_start' => now(),
                'current_period_end' => $currentPeriodEnd,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'apple_product_id' => $productId,
                'apple_transaction_id' => $appleTransactionId,
                'apple_original_transaction_id' => $originalTransactionId,
                'affiliate_id' => $record->affiliate_id ?? $provider->user?->referred_by_affiliate_id,
                'affiliate_referral_id' => $record->affiliate_referral_id ?? $provider->user?->affiliate_referral_id,
                'affiliate_attribution_type' => $record->affiliate_attribution_type ?? 'first_touch',
                'meta' => array_merge((array) ($record->meta ?? []), [
                    'source' => 'apple_iap_mobile',
                    'transaction' => $payload,
                    'user_id' => $userId,
                ]),
            ]);
            $record->save();

            if (in_array((string) $record->status, ['active', 'trialing', 'past_due'], true)) {
                $provider->updateExistingColumns([
                    'subscription_plan' => $plan->name,
                    'subscription_expires_at' => $currentPeriodEnd,
                    'stripe_subscription_status' => 'active',
                ]);
            }

            return $record->fresh(['plan']);
        });

        ProviderAppleSubscriptionLogger::logFulfillSuccess($userId, $record);

        return $record;
    }

    private function defaultPeriodEnd(SubscriptionPlan $plan): CarbonImmutable
    {
        $start = CarbonImmutable::now();

        return match ((string) $plan->billing_cycle) {
            'yearly' => $start->addYear(),
            'quarterly' => $start->addMonths(3),
            default => $start->addMonth(),
        };
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{status: string, current_period_end: ?CarbonImmutable, cancel_at_period_end: bool}
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
                    ? CarbonImmutable::createFromTimestampUTC((int) floor(((int) $expiresMs) / 1000))
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
