<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdAnalyticsEvent;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $adsQuery = Ad::query()->where('user_id', $user->id);
        $adIds = $adsQuery->pluck('id');

        $totalAds = (clone $adsQuery)->count();
        $publishedAds = (clone $adsQuery)->where('status', 'published')->count();
        $pendingPaymentAds = (clone $adsQuery)->where('status', 'pending_payment')->count();
        $draftAds = (clone $adsQuery)->where('status', 'draft')->count();

        $views = AdAnalyticsEvent::query()
            ->whereIn('ad_id', $adIds)
            ->where('event_type', 'view')
            ->count();
        $clicks = AdAnalyticsEvent::query()
            ->whereIn('ad_id', $adIds)
            ->where('event_type', 'click')
            ->count();

        $recentAds = Ad::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(6)
            ->get(['id', 'uuid', 'title', 'status', 'price_cents', 'currency', 'published_at', 'created_at'])
            ->map(function (Ad $ad): array {
                $viewCount = $ad->analyticsEvents()->where('event_type', 'view')->count();
                $clickCount = $ad->analyticsEvents()->where('event_type', 'click')->count();

                return [
                    'uuid' => $ad->uuid,
                    'title' => $ad->title,
                    'status' => $ad->status,
                    'price_cents' => $ad->price_cents,
                    'currency' => $ad->currency,
                    'published_at' => optional($ad->published_at)?->toIso8601String(),
                    'created_at' => optional($ad->created_at)?->toIso8601String(),
                    'analytics' => [
                        'views' => $viewCount,
                        'clicks' => $clickCount,
                        'ctr' => $viewCount > 0 ? round(($clickCount / $viewCount) * 100, 2) : 0.0,
                    ],
                ];
            });

        return Inertia::render('Advertiser/Dashboard', [
            'stats' => [
                'total_ads' => $totalAds,
                'published_ads' => $publishedAds,
                'pending_payment_ads' => $pendingPaymentAds,
                'draft_ads' => $draftAds,
                'views' => $views,
                'clicks' => $clicks,
                'ctr' => $views > 0 ? round(($clicks / $views) * 100, 2) : 0.0,
            ],
            'recentAds' => $recentAds,
        ]);
    }
}

