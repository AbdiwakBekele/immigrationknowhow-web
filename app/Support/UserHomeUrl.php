<?php

namespace App\Support;

use App\Models\User;

final class UserHomeUrl
{
    /**
     * First page after sign-in or impersonation (no intended URL / checkout shortcuts).
     */
    public static function afterAuthentication(User $user): string
    {
        if ($user->isAffiliate() && ! $user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if ($user->isAffiliate()) {
            return route('affiliate.dashboard');
        }

        if ($user->isProvider() && ! $user->phone_verified_at && ! $user->isAdmin()) {
            return route('address-detail');
        }

        if (! $user->hasCompletedOnboarding()) {
            return route('onboarding.index');
        }

        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->isProvider()) {
            return route('provider.dashboard');
        }

        return route('dashboard');
    }
}
