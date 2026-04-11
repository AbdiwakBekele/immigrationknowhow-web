<?php

namespace App\Actions\Affiliates;

use App\Models\Affiliate;
use App\Models\AffiliateReferralVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class CaptureAffiliateVisitAction
{
    public function handle(Request $request, Affiliate $affiliate): AffiliateReferralVisit
    {
        $windowDays = (int) config('affiliates.attribution_window_days', 30);
        $fingerprintHash = hash('sha256', implode('|', [
            $request->ip(),
            (string) $request->userAgent(),
            (string) $request->session()->getId(),
        ]));

        $visitedAt = now();
        $expiresAt = $visitedAt->copy()->addDays($windowDays);

        $isUnique = ! AffiliateReferralVisit::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('fingerprint_hash', $fingerprintHash)
            ->where('visited_at', '>=', $visitedAt->copy()->subDay())
            ->exists();

        $visit = AffiliateReferralVisit::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'session_id' => $request->session()->getId(),
            'fingerprint_hash' => $fingerprintHash,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 65535, ''),
            'referrer_url' => $request->headers->get('referer'),
            'landing_url' => $request->fullUrl(),
            'landing_path' => $request->path(),
            'query_params' => $request->query(),
            'is_unique' => $isUnique,
            'visited_at' => $visitedAt,
            'attribution_expires_at' => $expiresAt,
        ]);

        Cookie::queue(
            cookie(
                config('affiliates.attribution_cookie', 'affiliate_attribution'),
                json_encode([
                    'affiliate_id' => $affiliate->id,
                    'affiliate_code' => $affiliate->code,
                    'visit_id' => $visit->id,
                    'expires_at' => $expiresAt->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
                $windowDays * 24 * 60,
            )
        );

        $request->session()->put(config('affiliates.attribution_cookie', 'affiliate_attribution'), [
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'visit_id' => $visit->id,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        $affiliate->updateQuietly(['last_attribution_at' => $visitedAt]);

        return $visit;
    }
}
