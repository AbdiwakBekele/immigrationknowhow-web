<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneVerified
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->phone_verified_at || $user->isAdmin() || ! $user->followsProviderOnboarding()) {
            return $next($request);
        }

        if (! $user->isProvider()) {
            $user->assignRole('provider');
        }

        if ($request->routeIs('address-detail', 'address-detail.*', 'logout')) {
            return $next($request);
        }

        return redirect()->route('address-detail');
    }
}
