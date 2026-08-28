<?php

namespace App\Support;

use App\Models\EbookCoupon;
use App\Models\EbookShareCampaign;
use App\Models\EbookShareEvent;
use App\Models\User;

final class EbookShareCampaignPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forUser(User $user): array
    {
        $requiredShares = (int) config('library.share_campaign.required_shares', 5);
        $onePerUser = (bool) config('library.share_campaign.one_per_user', true);
        $campaign = EbookShareCampaign::latestForUser((int) $user->id);
        $eligibleRoles = config('library.share_campaign.eligible_roles', ['user', 'provider']);
        $role = $user->roles->first()?->name;
        $eligible = $role === null || in_array($role, $eligibleRoles, true);

        $events = $campaign
            ? $campaign->events()->with('libraryItem:id,title,slug,cover_image,type')->orderBy('id')->get()
            : collect();

        $confirmedCount = $events->where('status', EbookShareEvent::STATUS_CONFIRMED)->count();
        $shareCoupon = EbookCoupon::activeSocialShareCouponForUser((int) $user->id);

        $canStart = $eligible
            && (! $onePerUser || ! in_array($campaign?->status, [
                EbookShareCampaign::STATUS_COMPLETED,
                EbookShareCampaign::STATUS_REWARDED,
            ], true));

        return [
            'eligible' => $eligible,
            'can_start' => $canStart,
            'required_shares' => $requiredShares,
            'confirmed_shares' => $confirmedCount,
            'remaining_shares' => max(0, $requiredShares - $confirmedCount),
            'status' => $campaign?->status ?? 'not_started',
            'completed' => in_array($campaign?->status, [
                EbookShareCampaign::STATUS_COMPLETED,
                EbookShareCampaign::STATUS_REWARDED,
            ], true),
            'rewarded' => $campaign?->status === EbookShareCampaign::STATUS_REWARDED,
            'coupon_code' => $shareCoupon?->code,
            'events' => $events->map(fn (EbookShareEvent $event) => [
                'id' => $event->id,
                'library_item_id' => $event->library_item_id,
                'title' => $event->libraryItem?->title,
                'slug' => $event->libraryItem?->slug,
                'cover_image_url' => $event->coverImageUrl(),
                'platform' => $event->platform,
                'status' => $event->status,
                'share_url' => $event->shareUrl(),
                'intent_at' => $event->intent_at?->toIso8601String(),
                'confirmed_at' => $event->confirmed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
