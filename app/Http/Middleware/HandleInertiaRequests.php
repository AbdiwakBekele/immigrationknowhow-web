<?php

namespace App\Http\Middleware;

use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\AiAssistantSubscription;
use App\Models\ServiceTypeOption;
use App\Models\User;
use App\Support\ImpersonationActorId;
use App\Support\UserRoleAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                    'is_advertiser' => $request->user()->isAdvertiser(),
                    'ai_assistant_addon_active' => Schema::hasTable('ai_assistant_subscriptions')
                        ? AiAssistantSubscription::query()
                            ->where('user_id', $request->user()->id)
                            ->whereIn('status', ['active', 'trialing', 'past_due'])
                            ->exists()
                        : false,
                ] : null,
                'active_portal' => fn () => $request->user()
                    ? UserRoleAccounts::resolvedActivePortal(
                        $request->user(),
                        $request->session()->get(UserRoleAccounts::SESSION_ACTIVE_PORTAL)
                    )
                    : null,
                'role_accounts' => fn () => $request->user()
                    ? UserRoleAccounts::meta($request->user())
                    : null,
            ],
            'branding' => fn () => PlatformSetting::branding(),
            'dvLottery' => fn () => PlatformSetting::current()->dvLotteryContent(),
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
                'ai_assistant_response' => fn () => $request->session()->get('ai_assistant_response'),
            ],
            'library_cart_count' => static function () use ($request): int {
                if (! $request->user()) {
                    return 0;
                }

                $raw = $request->session()->get('library_cart_ids', []);
                if (! is_array($raw)) {
                    return 0;
                }

                return count(array_unique(array_filter(array_map('intval', $raw))));
            },
            'impersonation' => static function () use ($request) {
                $impersonatorId = \App\Support\ImpersonationActorId::fromSession(
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
            'provider_requires_background_check' => fn () => $this->providerRequiresBackgroundCheck($request),
        ];
    }

    private function providerRequiresBackgroundCheck(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $provider = $user->serviceProvider;
        if (! $provider || ! is_array($provider->service_types) || $provider->service_types === []) {
            return false;
        }

        $providerTypes = collect($provider->service_types)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => $this->canonicalServiceTypeValue((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($providerTypes->isEmpty()) {
            return false;
        }

        if (
            Schema::hasTable('service_type_options')
            && Schema::hasColumn('service_type_options', 'requires_background_check')
        ) {
            $requiredTypes = ServiceTypeOption::query()
                ->where('requires_background_check', true)
                ->pluck('value')
                ->map(fn ($value) => $this->canonicalServiceTypeValue((string) $value))
                ->filter()
                ->unique();

            return $providerTypes->intersect($requiredTypes)->isNotEmpty();
        }

        return $this->includesDefaultBackgroundCheckTypes($providerTypes);
    }

    private function includesDefaultBackgroundCheckTypes(Collection $providerTypes): bool
    {
        return $providerTypes
            ->intersect(['pet_sitter', 'petsitter', 'babysitter', 'baby_sitter', 'tutor'])
            ->isNotEmpty();
    }

    private function normalizeServiceTypeValue(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($value))), '_');
    }

    private function canonicalServiceTypeValue(string $value): string
    {
        $normalized = $this->normalizeServiceTypeValue($value);

        if (($normalized === 'pet_sitter' || str_contains($normalized, 'pet')) && str_contains($normalized, 'sitter')) {
            return 'pet_sitter';
        }

        if (str_contains($normalized, 'babysitter') || (str_contains($normalized, 'baby') && str_contains($normalized, 'sitter'))) {
            return 'babysitter';
        }

        if (str_contains($normalized, 'tutor')) {
            return 'tutor';
        }

        if ((str_contains($normalized, 'health') || str_contains($normalized, 'healthcare')) && str_contains($normalized, 'navigator')) {
            return 'health_navigator';
        }

        return $normalized;
    }
}
