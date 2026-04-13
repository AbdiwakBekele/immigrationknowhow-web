<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     * Busts Inertia partial reloads when Vite output changes (e.g. after `npm run build`).
     */
    public function version(Request $request): ?string
    {
        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            return (string) filemtime($manifest);
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'initials' => $request->user()->initials,
                    'full_name' => $request->user()->full_name,
                    'email' => $request->user()->email,
                    'email_verified_at' => $request->user()->email_verified_at,
                    'avatar' => $request->user()->avatar,
                    'avatar_url' => $request->user()->avatar_url,
                    'roles' => $request->user()->roles->pluck('name'),
                    'onboarding_completed_at' => $request->user()->onboarding_completed_at,
                    'is_affiliate' => $request->user()->isAffiliate(),
                ] : null,
            ],
            'branding' => fn () => PlatformSetting::branding(),
            'unread_notifications_count' => function () use ($request) {
                if (! $request->user()) {
                    return 0;
                }
                if (! Schema::hasTable('notifications')) {
                    return 0;
                }

                return $request->user()->unreadNotifications()->count();
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'otp_sent' => fn () => $request->session()->get('otp_sent'),
            ],
        ];
    }
}
