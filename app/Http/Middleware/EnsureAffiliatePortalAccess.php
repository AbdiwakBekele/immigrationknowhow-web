<?php

namespace App\Http\Middleware;

use App\Enums\AffiliateStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAffiliatePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isAffiliate(), 403);
        abort_unless($user->affiliateProfile, 403, 'Affiliate profile not found.');

        if ($user->affiliateProfile->status === AffiliateStatus::INACTIVE) {
            return redirect()->route('home')
                ->with('error', 'Your affiliate account is currently inactive.');
        }

        return $next($request);
    }
}
