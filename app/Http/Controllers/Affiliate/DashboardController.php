<?php

namespace App\Http\Controllers\Affiliate;

use App\Enums\AffiliateEarningStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $affiliate = $request->user()->affiliateProfile()->with(['user'])->firstOrFail();

        $stats = [
            'totalClicks' => $affiliate->visits()->count(),
            'uniqueClicks' => $affiliate->visits()->where('is_unique', true)->count(),
            'registrations' => $affiliate->referrals()->count(),
            'conversions' => $affiliate->earnings()->where('event_type', 'lead_converted')->count(),
            'pendingEarnings' => (float) $affiliate->earnings()->where('status', AffiliateEarningStatus::PENDING->value)->sum('commission_amount'),
            'approvedEarnings' => (float) $affiliate->earnings()->where('status', AffiliateEarningStatus::APPROVED->value)->sum('commission_amount'),
            'paidEarnings' => (float) $affiliate->earnings()->where('status', AffiliateEarningStatus::PAID->value)->sum('commission_amount'),
        ];

        $trend = $affiliate->visits()
            ->where('visited_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(visited_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $recentReferrals = $affiliate->referrals()
            ->with('referredUser:id,first_name,last_name,email,created_at')
            ->latest('registered_at')
            ->limit(10)
            ->get();

        $recentPayouts = $affiliate->payouts()
            ->latest('payout_date')
            ->limit(10)
            ->get();

        return Inertia::render('Affiliate/Dashboard', [
            'affiliate' => $affiliate,
            'stats' => $stats,
            'trend' => $trend,
            'recentReferrals' => $recentReferrals,
            'recentPayouts' => $recentPayouts,
        ]);
    }
}
