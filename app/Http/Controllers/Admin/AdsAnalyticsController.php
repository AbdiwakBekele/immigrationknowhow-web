<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdAnalyticsEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdsAnalyticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $ads = Ad::query()
            ->with(['user:id,first_name,last_name,email'])
            ->withCount([
                'analyticsEvents as views_count' => fn ($q) => $q->where('event_type', 'view'),
                'analyticsEvents as clicks_count' => fn ($q) => $q->where('event_type', 'click'),
            ])
            ->latest()
            ->limit(500)
            ->get()
            ->map(function (Ad $ad): array {
                $views = (int) $ad->views_count;
                $clicks = (int) $ad->clicks_count;

                return [
                    'uuid' => $ad->uuid,
                    'title' => $ad->title,
                    'status' => $ad->status,
                    'user' => [
                        'name' => trim(($ad->user?->first_name ?? '').' '.($ad->user?->last_name ?? '')) ?: '—',
                        'email' => $ad->user?->email ?? '—',
                    ],
                    'views' => $views,
                    'clicks' => $clicks,
                    'ctr' => $views > 0 ? round(($clicks / $views) * 100, 2) : 0.0,
                ];
            });

        $totalViews = AdAnalyticsEvent::query()->where('event_type', 'view')->count();
        $totalClicks = AdAnalyticsEvent::query()->where('event_type', 'click')->count();

        return Inertia::render('Admin/Ads/Analytics', [
            'summary' => [
                'views' => $totalViews,
                'clicks' => $totalClicks,
                'ctr' => $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0.0,
            ],
            'ads' => $ads,
        ]);
    }
}
