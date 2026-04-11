<?php

namespace App\Actions\Affiliates;

use App\Models\AffiliateInvite;
use App\Models\User;
use App\Notifications\AffiliateInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CreateAffiliateInviteAction
{
    public function handle(array $validated, User $admin): array
    {
        $token = Str::random(64);

        $invite = AffiliateInvite::create([
            'invited_by' => $admin->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'commission_type_override' => $validated['commission_type_override'] ?? null,
            'commission_value_override' => $validated['commission_value_override'] ?? null,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ]);

        Notification::route('mail', $invite->email)
            ->notify(new AffiliateInvitationNotification($invite, $token));

        return [$invite, $token];
    }

    public function resend(AffiliateInvite $invite): string
    {
        return $this->refreshAndSend($invite);
    }

    protected function refreshAndSend(AffiliateInvite $invite): string
    {
        $token = Str::random(64);

        $invite->forceFill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ])->save();

        Notification::route('mail', $invite->email)
            ->notify(new AffiliateInvitationNotification($invite->fresh(), $token));

        return $token;
    }
}
