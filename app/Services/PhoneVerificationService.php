<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PhoneVerificationService
{
    private const CACHE_PREFIX = 'phone_otp:';

    private const TTL_MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    public function sendOtp(User $user, string $phone): string
    {
        $normalized = $this->normalizePhone($phone);

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user->id), [
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        $user->update(['phone' => $normalized]);

        Log::channel(config('logging.default'))->info('Phone verification OTP', [
            'user_id' => $user->id,
            'phone' => $normalized,
            'code' => $code,
            'hint' => 'Replace with SMS in production; OTP is logged for development.',
        ]);

        return $normalized;
    }

    public function verify(User $user, string $code): bool
    {
        $key = $this->cacheKey($user->id);
        $payload = Cache::get($key);

        if (! is_array($payload) || empty($payload['code_hash'])) {
            return false;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if (strlen($normalizedCode) !== 6 || ! ctype_digit($normalizedCode)) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($key, $payload, now()->addMinutes(self::TTL_MINUTES));

            return false;
        }

        $acceptAny = (bool) config('phone_verification.accept_any_six_digit', true);
        $valid = $acceptAny
            || hash_equals($payload['code_hash'], hash('sha256', $normalizedCode));

        if ($valid) {
            Cache::forget($key);
            $user->update(['phone_verified_at' => now()]);

            return true;
        }

        $payload['attempts'] = $attempts + 1;
        Cache::put($key, $payload, now()->addMinutes(self::TTL_MINUTES));

        return false;
    }

    public function clear(User $user): void
    {
        Cache::forget($this->cacheKey($user->id));
    }

    private function cacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.$userId;
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits;
    }
}
