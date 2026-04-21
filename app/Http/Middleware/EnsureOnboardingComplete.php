<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasCompletedOnboarding()) {
            // Allow access to onboarding routes
            if ($request->routeIs('onboarding.*')) {
                return $next($request);
            }

            // Redirect to onboarding
            return redirect()->route($user->isAdvertiser() ? 'onboarding.advertiser' : 'onboarding.index');
        }

        return $next($request);
    }
}
