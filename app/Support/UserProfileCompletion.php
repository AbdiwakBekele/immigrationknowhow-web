<?php

namespace App\Support;

use App\Models\User;

class UserProfileCompletion
{
    /**
     * Profile completeness (0–100), aligned with the buyer profile editor.
     */
    public static function percent(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $profileData = data_get($user->onboarding_data ?? [], 'profile', []);
        $checks = [
            filled($user->first_name) && filled($user->last_name),
            filled($user->email),
            filled($user->phone),
            filled($user->city) || filled($user->state) || filled($user->country),
            ! empty($user->languages),
            filled($user->avatar),
            filled(data_get($profileData, 'country_of_origin')) || filled(data_get($profileData, 'immigration_status')),
            ! empty(data_get($profileData, 'social_links', [])),
            ! empty(data_get($profileData, 'hobbies', [])),
            (bool) data_get($profileData, 'has_children', false) || (bool) data_get($profileData, 'has_pets', false),
        ];

        $done = collect($checks)->filter()->count();

        return (int) round(($done / count($checks)) * 100);
    }
}
