<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

class TwilioService
{
    protected ?TwilioClient $client = null;
    protected ?string $verifySid = null;

    private function verifyServiceSid(): string
    {
        // Always read fresh from config so env/cache differences don't break runtime,
        // and so this service works even before client() is called.
        $sid = (string) config('services.twilio.verify_service_sid', '');

        if ($sid !== '') {
            $this->verifySid = $sid;
        }

        return $sid;
    }

    protected function client(): TwilioClient
    {
        if (! $this->client) {
            $this->client = new TwilioClient(
                (string) config('services.twilio.account_sid'),
                (string) config('services.twilio.auth_token'),
            );
            $this->verifySid = $this->verifyServiceSid();
        }

        return $this->client;
    }

    private function desiredVerifyCodeLength(): int
    {
        $configured = (int) config('services.twilio.verify_code_length', 6);

        if ($configured < 4 || $configured > 10) {
            Log::warning('Invalid Twilio verify code length configured. Falling back to 6 digits.', [
                'configured_length' => $configured,
            ]);

            return 6;
        }

        return $configured;
    }

    private function ensureVerifyServiceCodeLength(): void
    {
        $verifySid = $this->verifySid;

        if (! $verifySid) {
            return;
        }

        $desiredLength = $this->desiredVerifyCodeLength();
        $cacheKey = "twilio:verify:service:{$verifySid}:code-length:{$desiredLength}";

        if (Cache::get($cacheKey)) {
            return;
        }

        try {
            $service = $this->client()->verify->v2->services($verifySid)->fetch();
            $currentLength = (int) ($service->codeLength ?? 0);

            if ($currentLength !== $desiredLength) {
                $this->client()->verify->v2->services($verifySid)->update([
                    'codeLength' => $desiredLength,
                ]);
            }

            Cache::put($cacheKey, true, now()->addMinutes(30));
        } catch (\Twilio\Exceptions\RestException $e) {
            Log::error('Twilio Verify service sync failed', [
                'desired_code_length' => $desiredLength,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw new Exception('Failed to configure phone verification. Please try again.');
        }
    }

    public function isFakeMode(): bool
    {
        if ((bool) config('services.twilio.fake', false)) {
            return true;
        }

        // If Verify SID is not set (e.g. local dev), use fake mode so OTP flow works.
        if (empty((string) config('services.twilio.verify_service_sid'))) {
            return true;
        }

        return false;
    }

    public function sendVerificationOtp(string $phoneE164): void
    {
        if ($this->isFakeMode()) {
            Log::info("FAKE OTP sent to {$phoneE164}: 123456");
            return;
        }

        $verifySid = $this->verifyServiceSid();
        if ($verifySid === '') {
            throw new Exception('Twilio Verify Service SID is missing.');
        }

        try {
            // Ensure client + verifySid are initialized before syncing service config.
            $this->client();
            $this->ensureVerifyServiceCodeLength();

            $this->client()->verify->v2
                ->services($verifySid)
                ->verifications
                ->create($phoneE164, 'sms');
        } catch (\Twilio\Exceptions\RestException $e) {
            Log::error('Twilio Verify send failed', [
                'phone' => $phoneE164,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'status' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null,
                'details' => method_exists($e, 'getDetails') ? $e->getDetails() : null,
                'more_info' => method_exists($e, 'getMoreInfo') ? $e->getMoreInfo() : null,
            ]);

            $twilioMsg = $e->getMessage();
            $hint = str_contains($twilioMsg, 'is not a valid phone number')
                ? 'The phone number format is invalid. Please check the country code and number.'
                : 'Failed to send verification code. Please check the phone number and try again.';

            throw new Exception($hint);
        }
    }

    public function checkVerificationOtp(string $phoneE164, string $code): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if ($this->isFakeMode()) {
            return $normalizedCode === '123456';
        }

        $verifySid = $this->verifyServiceSid();
        if ($verifySid === '') {
            return false;
        }

        try {
            $this->client();
            $result = $this->client()->verify->v2
                ->services($verifySid)
                ->verificationChecks
                ->create([
                    'to' => $phoneE164,
                    'code' => $normalizedCode,
                ]);

            return ($result->status ?? null) === 'approved';
        } catch (\Twilio\Exceptions\RestException $e) {
            Log::warning('Twilio Verify check failed', [
                'phone' => $phoneE164,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

