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

        if (! $user || $user->phone_verified_at || $user->isAdmin()) {
            return $next($request);
        }

        if ($request->routeIs('verify-phone', 'verify-phone.*', 'logout')) {
            return $next($request);
        }

        return redirect()->route('verify-phone');
    }
}
