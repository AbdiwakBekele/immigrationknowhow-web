<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\AffiliateLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /**
     * Track a click on an affiliate link and redirect.
     */
    public function track(string $trackingCode, Request $request): RedirectResponse
    {
        $link = AffiliateLink::where('tracking_code', $trackingCode)
            ->where('is_active', true)
            ->first();

        if (!$link) {
            // Link not found or inactive, redirect to homepage
            return redirect('/');
        }

        // Record the click
        AffiliateClick::create([
            'affiliate_link_id' => $link->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'user_id' => auth()->id(),
        ]);

        // Increment click count
        $link->increment('clicks_count');

        // Redirect to the destination URL
        return redirect()->away($link->destination_url);
    }
}
