<?php

namespace App\Actions\Advertiser;

use App\Models\Ad;

final class ApplyAdOwnerEditStatus
{
    /**
     * When a published or suspended ad is edited, require admin approval again before it goes live.
     */
    public function __invoke(Ad $ad, string $previousStatus): Ad
    {
        if (! in_array($previousStatus, ['published', 'suspended'], true)) {
            return $ad;
        }

        if (! (bool) config('ads.require_admin_approval', true)) {
            return $ad;
        }

        $ad->update([
            'status' => 'pending_approval',
            'published_at' => null,
        ]);

        return $ad->fresh();
    }
}
