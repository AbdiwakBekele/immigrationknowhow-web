<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ImpersonationActorId;
use App\Support\UserHomeUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RedirectImpersonatedFromAdmin
{
    /**
     * If an admin URL is visited while impersonating a non-admin user (e.g. via browser Back),
     * automatically exit impersonation: restore the original admin session and clear any session state.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $originalUserId = ImpersonationActorId::fromSession(
            $request->session()->get('impersonating')
        );

        // Not impersonating, or already an admin (including super_admin) — proceed normally.
        if (! $originalUserId || $user->isAdmin()) {
            return $next($request);
        }

        $original = User::query()
            ->whereKey($originalUserId)
            ->with('roles')
            ->first();

        if (! $original) {
            Auth::guard('web')->logout();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('warning', 'Your administrator session could not be restored. Please sign in again.');
        }

        // Restore the original admin and clear any impersonated session state.
        Auth::guard('web')->logout();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::guard('web')->login($original);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $request->session()->regenerate();

        // For GET/HEAD we can safely re-load the intended admin URL.
        if ($request->isMethod('get') || $request->isMethod('head')) {
            return redirect()->to($request->fullUrl());
        }

        // For non-idempotent requests, avoid re-submitting by redirecting to a safe admin page.
        return redirect()->to(UserHomeUrl::afterAuthentication($original, isImpersonating: true));
    }
}

