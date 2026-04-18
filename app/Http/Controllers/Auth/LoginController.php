<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
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
        if ($user->isAffiliate() && ! $user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if ($user->isAffiliate()) {
            return route('affiliate.dashboard');
        }

        if ($user->isProvider() && ! $user->phone_verified_at && ! $user->isAdmin()) {
            return route('address-detail');
        }

        if (! $user->hasCompletedOnboarding()) {
            return route('onboarding.index');
        }

        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->isProvider()) {
            return route('provider.dashboard');
        }

        return route('dashboard');
    }

    protected function redirectAfterAuthentication(Request $request, User $user): RedirectResponse
    {
        if ($user->isAffiliate() && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->isAffiliate()) {
            return redirect()->intended(route('affiliate.dashboard'));
        }

        if ($user->isProvider() && ! $user->phone_verified_at && ! $user->isAdmin()) {
            return redirect()->route('address-detail');
        }

        if ($this->hasIntendedLibraryCheckout($request) && ! $user->isProvider() && ! $user->isAffiliate()) {
            return redirect()->intended(route('dashboard'));
        }

        if (! $user->hasCompletedOnboarding()) {
            return redirect()->route('onboarding.index');
        }

        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($user->isProvider()) {
            return redirect()->intended(route('provider.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
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
