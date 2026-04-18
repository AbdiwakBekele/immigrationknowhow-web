<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneVerified
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasCompletedSignupPhoneStep() || $user->isAdmin() || $user->isAffiliate()) {
            return $next($request);
        }

        if ($request->routeIs('address-detail', 'address-detail.*', 'logout', 'onboarding.index')) {
            return $next($request);
        }

        if (! $user->hasCompletedSignupAddressStep()) {
            return redirect()->route('address-detail');
        }

        return redirect()->route('onboarding.index', ['step' => 3]);
    }
}
