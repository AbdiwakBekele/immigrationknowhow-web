<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdAnalyticsEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicAdController extends Controller
{
    public function show(Request $request, Ad $ad): Response
    {
        abort_unless($ad->isPubliclyVisible(), 404);

        $this->recordEvent($request, $ad, 'view');

        return Inertia::render('Ads/Show', [
            'ad' => [
                'uuid' => $ad->uuid,
                'title' => $ad->title,
                'description' => $ad->description,
                'cta_url' => route('ads.public.click', $ad),
                'image_url' => $ad->image_url,
                'published_at' => optional($ad->published_at)?->toIso8601String(),
            ],
        ]);
    }

    public function click(Request $request, Ad $ad): RedirectResponse
    {
        abort_unless($ad->isPubliclyVisible(), 404);

        $this->recordEvent($request, $ad, 'click');

        return redirect()->away($ad->cta_url);
    }

    private function recordEvent(Request $request, Ad $ad, string $type): void
    {
        AdAnalyticsEvent::query()->create([
            'ad_id' => $ad->id,
            'event_type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
        ]);
    }
}

