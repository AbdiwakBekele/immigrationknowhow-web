<?php

namespace App\Http\Middleware;

use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\ImpersonationActorId;
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
        $hot = public_path('hot');
        if (is_file($hot)) {
            $contents = @file_get_contents($hot) ?: '';

            return hash('xxh128', 'vite-hot|'.$contents.'|'.(string) filemtime($hot));
        }

        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            return hash_file('xxh128', $manifest);
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
            'locale' => fn () => app()->getLocale(),
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
            'unreadMessages' => function () use ($request) {
                if (! $request->user()) {
                    return 0;
                }

                return Message::unreadIncomingCountFor($request->user());
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'otp_sent' => fn () => $request->session()->get('otp_sent'),
            ],
            'impersonation' => static function () use ($request) {
                $impersonatorId = ImpersonationActorId::fromSession(
                    $request->session()->get('impersonating')
                );

                if (! $impersonatorId || ! $request->user()) {
                    return null;
                }

                if ((int) $request->user()->id === $impersonatorId) {
                    return null;
                }

                $impersonator = User::query()
                    ->select(['id', 'first_name', 'last_name', 'email'])
                    ->whereKey($impersonatorId)
                    ->first();

                if (! $impersonator) {
                    return null;
                }

                return [
                    'original_user_id' => $impersonator->id,
                    'original_full_name' => $impersonator->full_name,
                    'original_email' => $impersonator->email,
                    'viewing_as_full_name' => $request->user()->full_name,
                    'viewing_as_email' => $request->user()->email,
                ];
            },
        ];
    }
}
