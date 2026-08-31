<?php

namespace App\Support;

use App\Enums\AdminTwoFactorDeliveryMethod;
use Illuminate\Http\Request;

class AdminTwoFactorSession
{
    public const VERIFIED_AT = 'admin_2fa_verified_at';

    public const PENDING = 'admin_2fa_pending';

    public const INTENDED_URL = 'admin_2fa_intended_url';

    public const DELIVERY_METHOD = 'admin_2fa_delivery_method';

    public const CODE_ISSUED_AT = 'admin_2fa_code_issued_at';

    public static function clear(Request $request): void
    {
        $request->session()->forget([
            self::VERIFIED_AT,
            self::PENDING,
            self::INTENDED_URL,
            self::DELIVERY_METHOD,
            self::CODE_ISSUED_AT,
        ]);
    }

    public static function markPending(Request $request): void
    {
        $request->session()->put(self::PENDING, true);
        $request->session()->forget(self::VERIFIED_AT);
    }

    public static function markVerified(Request $request): void
    {
        $request->session()->put(self::VERIFIED_AT, now()->toIso8601String());
        $request->session()->forget(self::PENDING);
    }

    public static function isVerified(Request $request): bool
    {
        $verifiedAt = $request->session()->get(self::VERIFIED_AT);

        if (! is_string($verifiedAt) || $verifiedAt === '') {
            return false;
        }

        try {
            $verified = \Illuminate\Support\Carbon::parse($verifiedAt);
        } catch (\Throwable) {
            return false;
        }

        $ttlHours = max(1, (int) config('admin_two_factor.session_ttl_hours', 12));

        return $verified->addHours($ttlHours)->isFuture();
    }

    public static function isPending(Request $request): bool
    {
        return (bool) $request->session()->get(self::PENDING, false);
    }

    public static function deliveryMethod(Request $request): ?AdminTwoFactorDeliveryMethod
    {
        $method = $request->session()->get(self::DELIVERY_METHOD);

        if (! is_string($method) || $method === '') {
            return null;
        }

        return AdminTwoFactorDeliveryMethod::tryFrom($method);
    }

    public static function setDeliveryMethod(Request $request, AdminTwoFactorDeliveryMethod $method): void
    {
        $request->session()->put(self::DELIVERY_METHOD, $method->value);
    }

    public static function hasCodeIssued(Request $request): bool
    {
        return is_string($request->session()->get(self::CODE_ISSUED_AT))
            && $request->session()->get(self::CODE_ISSUED_AT) !== '';
    }

    public static function markCodeIssued(Request $request): void
    {
        $request->session()->put(self::CODE_ISSUED_AT, now()->toIso8601String());
    }

    public static function rememberIntendedUrl(Request $request): void
    {
        if ($request->routeIs('admin.2fa.*', 'logout')) {
            return;
        }

        $request->session()->put(self::INTENDED_URL, $request->fullUrl());
    }

    public static function pullIntendedUrl(Request $request, string $default): string
    {
        $intended = $request->session()->pull(self::INTENDED_URL);

        return is_string($intended) && $intended !== '' ? $intended : $default;
    }
}
