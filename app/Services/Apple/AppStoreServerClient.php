<?php

namespace App\Services\Apple;

use App\Support\AppleIapConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AppStoreServerClient
{
    public function __construct(
        private readonly AppStoreJwtFactory $jwtFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            throw new RuntimeException('Missing transaction ID.');
        }

        $response = $this->request('GET', '/inApps/v1/transactions/'.rawurlencode($transactionId));
        $signed = $response['signedTransactionInfo'] ?? null;
        if (! is_string($signed) || trim($signed) === '') {
            throw new RuntimeException('Apple did not return transaction info.');
        }

        return $this->decodeJwsPayload($signed);
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

        return $this->request(
            'GET',
            '/inApps/v1/subscriptions/'.rawurlencode($originalTransactionId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path): array
    {
        $token = $this->jwtFactory->make();
        $url = rtrim(AppleIapConfig::apiBaseUrl(), '/').$path;

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
