<?php

namespace App\Support;

use App\Models\User;

final class UserHomeUrl
{
    /**
     * First page after sign-in or impersonation (no intended URL / checkout shortcuts).
     */
    public static function afterAuthentication(User $user, bool $isImpersonating = false): string
    {
        if (! $user->isAffiliate() && ! $user->hasCompletedSignupPhoneStep() && ! $user->isAdmin()) {
            if ($user->isAdvertiser()) {
                return $user->hasCompletedSignupAddressStep()
                    ? route('onboarding.advertiser', ['step' => 3])
                    : route('onboarding.advertiser', ['step' => 2]);
            }

            if ($user->followsProviderOnboarding()) {
                return $user->hasCompletedSignupAddressStep()
                    ? route('onboarding.provider', ['step' => 3])
                    : route('address-detail');
            }

            return route('onboarding.user', ['step' => 2]);
        }

        if ($user->isAdvertiser() && ! $user->hasCompletedOnboarding()) {
            return route('onboarding.advertiser');
        }

        if (! $user->isAffiliate() && ! $user->hasCompletedOnboarding()) {
            return $user->followsProviderOnboarding()
                ? route('onboarding.provider')
                : route('onboarding.user');
        }

        if ($isImpersonating) {
            if ($user->isAdmin()) {
                return route('admin.dashboard');
            }

            if ($user->isProvider()) {
                return route('provider.dashboard');
            }

            if ($user->isAdvertiser()) {
                return route('advertiser.dashboard');
            }

            if ($user->isAffiliate()) {
                return route('affiliate.dashboard');
            }

            return route('dashboard');
        }

        if ($user->isAffiliate() && ! $user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if ($user->isAffiliate()) {
            return route('affiliate.dashboard');
        }

        if ($user->isAdvertiser()) {
            return route('advertiser.dashboard');
        }

        if ($user->isAdvertiser() && ! $user->hasCompletedOnboarding()) {
            return route('onboarding.advertiser');
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

        if ($user->isAdvertiser()) {
            return route('advertiser.dashboard');
        }

        return route('dashboard');
    }
}
