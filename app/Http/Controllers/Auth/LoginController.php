<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RoleHelper;
use App\Support\UserHomeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        $user = Auth::user();

        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
            'authenticatedUser' => $user
                ? [
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'continueUrl' => $this->homeUrlForUser($user),
                ]
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->updateLastLogin();

        return $this->redirectAfterAuthentication($request, $user);
    }

    /**
     * Default “home” URL for the signed-in user (verify phone / onboarding / role dashboard).
     */
    protected function homeUrlForUser(User $user): string
    {
        return UserHomeUrl::afterAuthentication($user);
    }

    protected function redirectAfterAuthentication(Request $request, User $user): RedirectResponse
    {
        // Self-heal role assignment for legacy accounts that started provider/advertiser signup
        // before role metadata was consistently persisted.
        if (! $user->hasCompletedOnboarding()) {
            $registrationRole = strtolower((string) data_get($user->onboarding_data, 'registration.role', ''));
            $hasProviderIntent = $registrationRole === UserRole::PROVIDER->value
                || filled(data_get($user->onboarding_data, 'registration.service_type'))
                || filled(data_get($user->onboarding_data, 'service_type'));
            $hasAdvertiserIntent = $registrationRole === UserRole::ADVERTISER->value;

            if ($hasProviderIntent && ! $user->isProvider()) {
                RoleHelper::ensureExists(UserRole::PROVIDER->value);
                $user->assignRole(UserRole::PROVIDER->value);
            }

            if ($hasAdvertiserIntent && ! $user->isAdvertiser()) {
                RoleHelper::ensureExists(UserRole::ADVERTISER->value);
                $user->assignRole(UserRole::ADVERTISER->value);
            }
        }

        if ($this->hasIntendedLibraryCheckout($request) && ! $user->isProvider() && ! $user->isAffiliate() && ! $user->isAdvertiser()) {
            return redirect()->intended(route('dashboard'));
        }

        return redirect()->intended($this->homeUrlForUser($user));
    }

    private function hasIntendedLibraryCheckout(Request $request): bool
    {
        $intended = $request->session()->get('url.intended');

        return is_string($intended) && (bool) preg_match('#/library/[^/]+/pay(?:\?|$)#', $intended);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
