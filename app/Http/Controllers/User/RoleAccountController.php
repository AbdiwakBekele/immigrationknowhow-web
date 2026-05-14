<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\UserRoleAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleAccountController extends Controller
{
  public function createSeeker(): Response
  {
    $user = auth()->user();
    $meta = UserRoleAccounts::meta($user);

    abort_unless($meta['can_add_seeker'], 403);

    return Inertia::render('User/RoleAccounts/AddSeeker', [
      'roleAccounts' => $meta,
      'backUrl' => route('provider.profile.edit'),
    ]);
  }

  public function storeSeeker(Request $request): RedirectResponse
  {
    $user = auth()->user();
    UserRoleAccounts::enableSeeker($user, $request->all());

    $request->session()->put(UserRoleAccounts::SESSION_ACTIVE_PORTAL, UserRole::USER->value);

    return redirect()
      ->route('dashboard')
      ->with('success', 'Service seeker account added.');
  }

  public function startProvider(Request $request): RedirectResponse
  {
    $user = auth()->user();
    UserRoleAccounts::startProvider($user);

    $step = $user->hasCompletedSignupPhoneStep() ? 4 : 2;

    return redirect()
      ->route('onboarding.provider', ['step' => $step])
      ->with('success', 'Finish the provider setup steps to publish your provider account.');
  }

  public function switch(Request $request): RedirectResponse
  {
    $user = auth()->user();
    $validated = $request->validate([
      'portal' => ['required', 'string', Rule::in([UserRole::USER->value, UserRole::PROVIDER->value])],
    ]);

    $meta = UserRoleAccounts::meta($user);
    abort_unless($meta['can_switch'], 403);

    if ($validated['portal'] === UserRole::USER->value) {
      abort_unless($meta['has_seeker'], 403);
    } else {
      abort_unless($meta['has_provider'], 403);
    }

    $request->session()->put(UserRoleAccounts::SESSION_ACTIVE_PORTAL, $validated['portal']);

    return redirect(UserRoleAccounts::dashboardRouteFor($user, $validated['portal']));
  }
}
