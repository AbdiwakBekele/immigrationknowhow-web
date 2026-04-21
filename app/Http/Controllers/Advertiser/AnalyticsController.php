<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdAnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = auth()->user();
        $ads = Ad::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get(['id', 'uuid', 'title', 'status', 'published_at', 'created_at'])
            ->map(function (Ad $ad): array {
                $views = $ad->analyticsEvents()->where('event_type', 'view')->count();
                $clicks = $ad->analyticsEvents()->where('event_type', 'click')->count();

                return [
                    'uuid' => $ad->uuid,
                    'title' => $ad->title,
                    'status' => $ad->status,
                    'published_at' => optional($ad->published_at)?->toIso8601String(),
                    'created_at' => optional($ad->created_at)?->toIso8601String(),
                    'views' => $views,
                    'clicks' => $clicks,
                    'ctr' => $views > 0 ? round(($clicks / $views) * 100, 2) : 0.0,
                ];
            });

        $adIds = $ads->pluck('id')->all();
        $totalViews = empty($adIds)
            ? 0
            : AdAnalyticsEvent::query()->whereIn('ad_id', $adIds)->where('event_type', 'view')->count();
        $totalClicks = empty($adIds)
            ? 0
            : AdAnalyticsEvent::query()->whereIn('ad_id', $adIds)->where('event_type', 'click')->count();

        return Inertia::render('Advertiser/Analytics', [
            'summary' => [
                'views' => $totalViews,
                'clicks' => $totalClicks,
                'ctr' => $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0.0,
            ],
            'ads' => $ads->map(fn (array $item) => collect($item)->except('id')->all()),
            'adsRouteNamePrefix' => $this->adsRouteNamePrefix($request),
            'adPortal' => $this->adPortalPayload($request),
        ]);
    }

    private function adsRouteNamePrefix(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        if (Str::startsWith($routeName, 'provider.ads.')) {
            return 'provider.ads';
        }

        if (Str::startsWith($routeName, 'user.ads.')) {
            return 'user.ads';
        }

        return 'advertiser.ads';
    }

    private function adPortalPayload(Request $request): array
    {
        $adsRouteNamePrefix = $this->adsRouteNamePrefix($request);
        $dashboardRouteName = match ($adsRouteNamePrefix) {
            'user.ads' => 'dashboard',
            'provider.ads' => 'provider.dashboard',
            default => 'advertiser.dashboard',
        };

        $analyticsRouteName = match ($adsRouteNamePrefix) {
            'user.ads' => 'user.ads.analytics',
            'provider.ads' => 'provider.ads.analytics',
            default => 'advertiser.analytics',
        };

        return [
            'portal' => match ($adsRouteNamePrefix) {
                'user.ads' => 'user',
                'provider.ads' => 'provider',
                default => 'advertiser',
            },
            'dashboardHref' => route($dashboardRouteName),
            'adsHref' => route($adsRouteNamePrefix.'.index'),
            'analyticsHref' => route($analyticsRouteName),
        ];
    }
}

