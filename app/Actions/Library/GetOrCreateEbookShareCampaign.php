<?php

namespace App\Actions\Library;

use App\Models\EbookShareCampaign;
use App\Models\User;
use RuntimeException;

final class GetOrCreateEbookShareCampaign
{
    public function __invoke(User $user): EbookShareCampaign
    {
        $eligibleRoles = config('library.share_campaign.eligible_roles', ['user', 'provider']);
        $role = $user->roles->first()?->name;
        if ($role !== null && ! in_array($role, $eligibleRoles, true)) {
            throw new RuntimeException('Your account is not eligible for the share campaign.');
        }

        $active = EbookShareCampaign::activeForUser((int) $user->id);
        if ($active) {
            return $active;
        }

        if (config('library.share_campaign.one_per_user', true)) {
            $previousReward = EbookShareCampaign::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [EbookShareCampaign::STATUS_COMPLETED, EbookShareCampaign::STATUS_REWARDED])
                ->exists();

            if ($previousReward) {
                throw new RuntimeException('You have already completed the share campaign.');
            }
        }

        return EbookShareCampaign::query()->create([
            'user_id' => $user->id,
            'status' => EbookShareCampaign::STATUS_IN_PROGRESS,
            'required_shares' => (int) config('library.share_campaign.required_shares', 5),
        ]);
    }
}
