<?php

namespace App\Http\Middleware;

use App\Services\AdminTwoFactorService;
use App\Support\AdminTwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorVerified
{
    public function __construct(
        protected AdminTwoFactorService $adminTwoFactor,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->adminTwoFactor->requiresChallenge($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'admin.2fa.*',
            'logout',
            'admin.profile.index',
            'admin.profile.update',
        )) {
            return $next($request);
        }

        if (AdminTwoFactorSession::isVerified($request)) {
            return $next($request);
        }

        AdminTwoFactorSession::rememberIntendedUrl($request);

        return redirect()->route('admin.2fa.challenge');
    }
}
