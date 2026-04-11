<?php

namespace App\Actions\Affiliates;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterAffiliateAction
{
    public function handle(array $validated): User
    {
        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
            ]);

            RoleHelper::ensureExists(UserRole::AFFILIATE->value);
            $user->assignRole(UserRole::AFFILIATE->value);

            Affiliate::create([
                'user_id' => $user->id,
                'status' => AffiliateStatus::PENDING_VERIFICATION,
                'phone' => $validated['phone'] ?? null,
                'company_name' => $validated['company_name'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'social_profile_url' => $validated['social_profile_url'] ?? null,
                'payout_method' => $validated['payout_method'] ?? null,
                'payout_details' => array_filter([
                    'paypal_email' => $validated['paypal_email'] ?? null,
                    'bank_account_name' => $validated['bank_account_name'] ?? null,
                ]),
            ]);

            return $user->fresh(['affiliateProfile']);
        });
    }
}
