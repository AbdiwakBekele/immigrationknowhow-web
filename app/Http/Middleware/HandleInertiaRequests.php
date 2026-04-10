<?php

namespace App\Http\Middleware;

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
     */
    public function version(Request $request): ?string
    {
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
                    'full_name' => $request->user()->full_name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                    'roles' => $request->user()->roles->pluck('name'),
                    'onboarding_completed_at' => $request->user()->onboarding_completed_at,
                ] : null,
            ],
            'unread_notifications_count' => function () use ($request) {
                if (! $request->user() || ! str_starts_with($request->path(), 'admin')) {
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
