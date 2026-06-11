<?php

namespace App\Services\Apple;

use App\Support\AppleIapConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AppStoreServerClient
{
    public const ENV_PRODUCTION = 'production';

    public const ENV_SANDBOX = 'sandbox';

    private ?string $lastSuccessfulEnvironment = null;

    public function __construct(
        private readonly AppStoreJwtFactory $jwtFactory,
    ) {}

    public function lastSuccessfulEnvironment(): ?string
    {
        return $this->lastSuccessfulEnvironment;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            throw new RuntimeException('Missing transaction ID.');
        }

        $response = $this->requestWithEnvironmentFallback(
            'GET',
            '/inApps/v1/transactions/'.rawurlencode($transactionId),
        );
        $signed = $response['signedTransactionInfo'] ?? null;
        if (! is_string($signed) || trim($signed) === '') {
            throw new RuntimeException('Apple did not return transaction info.');
        }

        $payload = $this->decodeJwsPayload($signed);
        if ($this->lastSuccessfulEnvironment !== null) {
            $payload['_apple_environment'] = $this->lastSuccessfulEnvironment;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionStatuses(string $originalTransactionId): array
    {
        $originalTransactionId = trim($originalTransactionId);
        if ($originalTransactionId === '') {
            throw new RuntimeException('Missing original transaction ID.');
        }

        return $this->requestWithEnvironmentFallback(
            'GET',
            '/inApps/v1/subscriptions/'.rawurlencode($originalTransactionId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestWithEnvironmentFallback(string $method, string $path): array
    {
        $environments = $this->environmentsToTry();
        $lastError = null;

        foreach ($environments as $environment) {
            try {
                $json = $this->requestInEnvironment($method, $path, $environment);
                $this->lastSuccessfulEnvironment = $environment;

                return $json;
            } catch (RuntimeException $e) {
                $lastError = $e;

                if (! $this->shouldTryAlternateEnvironment($e, $environment, $environments)) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new RuntimeException('Apple API request failed.');
    }

    /**
     * @return list<string>
     */
    private function environmentsToTry(): array
    {
        // App Review / sandbox purchases: try production first, then sandbox (21007-style fallback).
        return [self::ENV_PRODUCTION, self::ENV_SANDBOX];
    }

    /**
     * @param  list<string>  $environments
     */
    private function shouldTryAlternateEnvironment(
        RuntimeException $error,
        string $currentEnvironment,
        array $environments,
    ): bool {
        $currentIndex = array_search($currentEnvironment, $environments, true);
        if ($currentIndex === false || $currentIndex >= count($environments) - 1) {
            return false;
        }

        $message = strtolower($error->getMessage());

        return str_contains($message, 'http 404')
            || str_contains($message, 'not found')
            || str_contains($message, 'transaction id')
            || str_contains($message, 'invalid transaction')
            || str_contains($message, 'does not exist');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestInEnvironment(string $method, string $path, string $environment): array
    {
        $token = $this->jwtFactory->make();
        $baseUrl = $environment === self::ENV_SANDBOX
            ? 'https://api.storekit-sandbox.itunes.apple.com'
            : 'https://api.storekit.itunes.apple.com';
        $url = rtrim($baseUrl, '/').$path;

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->send($method, $url);
        } catch (RequestException $e) {
            $message = $e->response?->json('errorMessage')
                ?? $e->response?->body()
                ?? $e->getMessage();

            throw new RuntimeException(is_string($message) ? $message : 'Apple API request failed.', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Apple API returned HTTP '.$response->status().'.');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Invalid Apple API response.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJwsPayload(string $jws): array
    {
        $parts = explode('.', trim($jws));
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid signed transaction.');
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Could not decode Apple transaction payload.');
        }

        return $payload;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Could not base64-decode Apple payload.');
        }

        return $decoded;
    }
}
