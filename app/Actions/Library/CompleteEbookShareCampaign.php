<?php

namespace App\Actions\Library;

use App\Models\EbookShareCampaign;
use Illuminate\Support\Facades\DB;

final class CompleteEbookShareCampaign
{
    public function __construct(
        private readonly IssueSocialShareEbookCoupon $issueCoupon,
    ) {}

    public function __invoke(EbookShareCampaign $campaign): ?EbookShareCampaign
    {
        return DB::transaction(function () use ($campaign) {
            $campaign->refresh();

            if ($campaign->status !== EbookShareCampaign::STATUS_IN_PROGRESS) {
                return $campaign;
            }

            if (! $campaign->isComplete()) {
                return $campaign;
            }

            $campaign->update([
                'status' => EbookShareCampaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $coupon = ($this->issueCoupon)($campaign->user()->firstOrFail(), $campaign);

            $campaign->update([
                'status' => EbookShareCampaign::STATUS_REWARDED,
                'rewarded_at' => now(),
                'ebook_coupon_id' => $coupon->id,
            ]);

            return $campaign->fresh(['coupon', 'events.libraryItem']);
        });
    }
}
