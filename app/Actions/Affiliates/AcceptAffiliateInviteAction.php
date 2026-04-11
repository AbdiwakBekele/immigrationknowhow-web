<?php

namespace App\Actions\Affiliates;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\AffiliateInvite;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptAffiliateInviteAction
{
    public function handle(AffiliateInvite $invite, array $validated): User
    {
        if (! $invite->isPending()) {
            throw ValidationException::withMessages([
                'email' => 'This invitation is no longer valid.',
            ]);
        }

        return DB::transaction(function () use ($invite, $validated) {
            [$firstName, $lastName] = $this->splitInviteName($invite->name);

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $invite->email,
                'password' => Hash::make($validated['password']),
                'phone' => $invite->phone,
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
            ]);

            RoleHelper::ensureExists(UserRole::AFFILIATE->value);
            $user->assignRole(UserRole::AFFILIATE->value);

            $affiliate = Affiliate::create([
                'user_id' => $user->id,
                'status' => AffiliateStatus::EMAIL_SENT,
                'phone' => $invite->phone,
                'notes' => $invite->notes,
                'commission_type_override' => $invite->commission_type_override,
                'commission_value_override' => $invite->commission_value_override,
                'invited_by' => $invite->invited_by,
            ]);

            $invite->update([
                'affiliate_id' => $affiliate->id,
                'accepted_at' => now(),
            ]);

            return $user->fresh(['affiliateProfile']);
        });
    }

    protected function splitInviteName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = $parts[0] ?? 'Affiliate';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Partner';

        return [$firstName, $lastName];
    }
}
