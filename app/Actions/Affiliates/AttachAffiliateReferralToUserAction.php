<?php

namespace App\Actions\Affiliates;

use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralVisit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttachAffiliateReferralToUserAction
{
    public function handle(User $user, Request $request): ?AffiliateReferral
    {
        if ($user->referred_by_affiliate_id || $user->affiliate_referral_id) {
            return $user->affiliateReferral;
        }

        $payload = $request->session()->get(config('affiliates.attribution_cookie', 'affiliate_attribution'));

        if (! $payload && $request->hasCookie(config('affiliates.attribution_cookie', 'affiliate_attribution'))) {
            $payload = json_decode((string) $request->cookie(config('affiliates.attribution_cookie', 'affiliate_attribution')), true);
        }

        if (! is_array($payload) || empty($payload['affiliate_id']) || empty($payload['visit_id'])) {
            return null;
        }

        $expiresAt = isset($payload['expires_at']) ? Carbon::parse($payload['expires_at']) : null;
        if ($expiresAt && $expiresAt->isPast()) {
            return null;
        }

        $affiliate = Affiliate::find($payload['affiliate_id']);
        $visit = AffiliateReferralVisit::find($payload['visit_id']);

        if (! $affiliate || ! $visit || $visit->affiliate_id !== $affiliate->id) {
            return null;
        }

        $firstVisitId = AffiliateReferralVisit::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('fingerprint_hash', $visit->fingerprint_hash)
            ->oldest('visited_at')
            ->value('id');

        $referral = AffiliateReferral::create([
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => $user->id,
            'first_visit_id' => $firstVisitId,
            'last_visit_id' => $visit->id,
            'attributed_visit_id' => $visit->id,
            'attribution_model' => 'last_click',
            'registered_at' => now(),
        ]);

        $visit->update(['registered_user_id' => $user->id]);

        $user->update([
            'referred_by_affiliate_id' => $affiliate->id,
            'affiliate_referral_id' => $referral->id,
        ]);

        return $referral;
    }
}
