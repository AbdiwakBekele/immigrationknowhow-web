<?php

namespace App\Services;

use App\Enums\AdminTwoFactorDeliveryMethod;
use App\Models\User;
use App\Support\AdminTwoFactorSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminTwoFactorService
{
    private const ATTEMPTS_PREFIX = 'admin_2fa_attempts:';

    private const SEND_LOCK_PREFIX = 'admin_2fa_send_lock:';

    private const OTP_PREFIX = 'admin_2fa_otp:';

    private const SEND_COOLDOWN_PREFIX = 'admin_2fa_send_cooldown:';

    public function __construct(
        protected TwilioService $twilio,
        protected PhoneVerificationService $phoneVerification,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('admin_two_factor.enabled', true);
    }

    public function requiresChallenge(User $user): bool
    {
        return $this->isEnabled() && $user->isAdmin();
    }

    public function usesFakeSms(): bool
    {
        return $this->twilio->isFakeMode();
    }

    public function fakeOtpCode(): string
    {
        return '123456';
    }

    public function adminPhone(User $user): ?string
    {
        $phone = trim((string) ($user->phone ?? ''));

        if ($phone === '') {
            return null;
        }

        return $this->phoneVerification->normalizePhone($phone, $user);
    }

    public function issueInitialOtp(User $user, Request $request): ?string
    {
        if (AdminTwoFactorSession::hasCodeIssued($request)) {
            return null;
        }

        if ($this->adminPhone($user) === null) {
            return null;
        }

        return $this->issueOtp($user, $request, invalidatePrevious: false);
    }

    public function resendOtp(User $user, Request $request): string
    {
        return $this->issueOtp($user, $request, invalidatePrevious: true);
    }

    public function verify(User $user, Request $request, string $code): bool
    {
        $phone = $this->adminPhone($user);

        if ($phone === null || AdminTwoFactorSession::deliveryMethod($request) === null) {
            return false;
        }

        if ($this->attemptsExceeded($user->id)) {
            return false;
        }

        $approved = $this->checkOtp($user, $phone, $code);

        if ($approved) {
            $this->clearAttempts($user->id);
            $this->clearOtp($user->id);

            Log::info('Admin 2FA verified', ['user_id' => $user->id]);

            return true;
        }

        $this->recordFailedAttempt($user->id);

        return false;
    }

    public function attemptsExceeded(int $userId): bool
    {
        $maxAttempts = max(1, (int) config('admin_two_factor.max_attempts', 5));
        $attempts = (int) Cache::get($this->attemptsKey($userId), 0);

        return $attempts >= $maxAttempts;
    }

    public function remainingAttempts(int $userId): int
    {
        $maxAttempts = max(1, (int) config('admin_two_factor.max_attempts', 5));
        $attempts = (int) Cache::get($this->attemptsKey($userId), 0);

        return max(0, $maxAttempts - $attempts);
    }

    public function clearAttempts(int $userId): void
    {
        Cache::forget($this->attemptsKey($userId));
    }

    public function statusMessage(bool $resent = false): string
    {
        $prefix = $resent ? 'New verification code sent' : 'Verification code sent';

        return $this->usesFakeSms()
            ? 'Development mode: use code 123456 (no SMS was sent).'
            : "{$prefix} by SMS.";
    }

    private function issueOtp(User $user, Request $request, bool $invalidatePrevious): string
    {
        $phone = $this->adminPhone($user);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'code' => 'Add a mobile phone number to your admin profile before completing two-factor authentication.',
            ]);
        }

        $lock = Cache::lock(self::SEND_LOCK_PREFIX.$user->id, 15);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'code' => 'Please wait a moment before requesting another code.',
            ]);
        }

        try {
            if ($invalidatePrevious) {
                $this->assertSendCooldownElapsed($user->id);
                $this->clearOtp($user->id);
            }

            $this->deliverSmsOtp($user, $phone);

            AdminTwoFactorSession::setDeliveryMethod($request, AdminTwoFactorDeliveryMethod::Sms);
            AdminTwoFactorSession::markCodeIssued($request);

            if ($invalidatePrevious) {
                $this->markSendCooldown($user->id);
            }

            Log::info('Admin 2FA OTP issued', [
                'user_id' => $user->id,
                'resent' => $invalidatePrevious,
            ]);

            return $this->statusMessage(resent: $invalidatePrevious);
        } finally {
            $lock->release();
        }
    }

    private function deliverSmsOtp(User $user, string $phone): void
    {
        if ($this->twilio->isFakeMode()) {
            $this->storeOtp($user->id, $this->fakeOtpCode());
            Log::info("FAKE OTP sent via sms to {$phone}: {$this->fakeOtpCode()}");

            return;
        }

        $this->twilio->sendVerificationOtp($phone, 'sms');
    }

    private function checkOtp(User $user, string $phone, string $code): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if ($this->twilio->isFakeMode()) {
            $expected = Cache::get($this->otpCacheKey($user->id));

            return is_string($expected) && hash_equals($expected, $normalizedCode);
        }

        return $this->twilio->checkVerificationOtp($phone, $code, 'sms');
    }

    private function storeOtp(int $userId, string $code): void
    {
        Cache::put($this->otpCacheKey($userId), $code, now()->addMinutes(15));
    }

    private function clearOtp(int $userId): void
    {
        Cache::forget($this->otpCacheKey($userId));
    }

    private function otpCacheKey(int $userId): string
    {
        return self::OTP_PREFIX.$userId;
    }

    private function recordFailedAttempt(int $userId): void
    {
        $key = $this->attemptsKey($userId);
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes(30));
    }

    private function attemptsKey(int $userId): string
    {
        return self::ATTEMPTS_PREFIX.$userId;
    }

    private function markSendCooldown(int $userId): void
    {
        $seconds = (int) config('admin_two_factor.send_cooldown_seconds', 30);

        if ($seconds <= 0) {
            return;
        }

        Cache::put($this->sendCooldownKey($userId), true, now()->addSeconds($seconds));
    }

    private function assertSendCooldownElapsed(int $userId): void
    {
        $seconds = (int) config('admin_two_factor.send_cooldown_seconds', 30);

        if ($seconds <= 0) {
            return;
        }

        if (Cache::has($this->sendCooldownKey($userId))) {
            throw ValidationException::withMessages([
                'code' => 'Please wait before requesting another code.',
            ]);
        }
    }

    private function sendCooldownKey(int $userId): string
    {
        return self::SEND_COOLDOWN_PREFIX.$userId;
    }
}
