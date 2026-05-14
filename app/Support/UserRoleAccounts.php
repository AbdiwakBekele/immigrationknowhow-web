<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserRoleAccounts
{
  public const SESSION_ACTIVE_PORTAL = 'active_account_portal';

  /**
   * @return array{
   *     roles: list<string>,
   *     has_seeker: bool,
   *     has_provider: bool,
   *     can_add_seeker: bool,
   *     can_add_provider: bool,
   *     can_switch: bool
   * }
   */
  public static function meta(User $user): array
  {
    $roles = $user->getRoleNames()->values()->all();
    $hasSeeker = $user->hasRole(UserRole::USER->value);
    $hasProvider = $user->isProvider() && $user->serviceProvider()->exists();

    return [
      'roles' => $roles,
      'has_seeker' => $hasSeeker,
      'has_provider' => $hasProvider,
      'can_add_seeker' => $user->isProvider() && ! $hasSeeker,
      'can_add_provider' => $hasSeeker && ! $hasProvider,
      'can_switch' => $hasSeeker && $hasProvider,
    ];
  }

  public static function resolvedActivePortal(User $user, ?string $sessionPortal = null): string
  {
    $meta = self::meta($user);

    if (! $meta['can_switch']) {
      return $meta['has_provider'] ? UserRole::PROVIDER->value : UserRole::USER->value;
    }

    if (
      in_array($sessionPortal, [UserRole::USER->value, UserRole::PROVIDER->value], true)
      && (($sessionPortal === UserRole::USER->value && $meta['has_seeker'])
        || ($sessionPortal === UserRole::PROVIDER->value && $meta['has_provider']))
    ) {
      return $sessionPortal;
    }

    return $meta['has_provider'] ? UserRole::PROVIDER->value : UserRole::USER->value;
  }

  public static function isAddingProviderAccount(User $user): bool
  {
    return $user->hasRole(UserRole::USER->value)
      && ! $user->serviceProvider()->exists()
      && data_get($user->onboarding_data, 'role_accounts.provider.status') === 'in_progress';
  }

  /**
   * @param  array<string, mixed>  $input
   */
  public static function enableSeeker(User $user, array $input): User
  {
    if ($user->hasRole(UserRole::USER->value)) {
      throw ValidationException::withMessages([
        'account' => 'You already have a service seeker account.',
      ]);
    }

    if (! $user->isProvider()) {
      throw ValidationException::withMessages([
        'account' => 'Complete your service provider account before adding a seeker profile.',
      ]);
    }

    $userServiceTypeValues = ServiceTypeOptions::values('user');

    $validated = validator($input, [
      'number_of_children' => ['nullable', 'integer', 'min:0', 'max:50'],
      'children_ages_text' => ['nullable', 'string', 'max:255'],
      'dogs_count' => ['nullable', 'integer', 'min:0', 'max:50'],
      'services_needed' => ['nullable', 'array', 'max:8'],
      'services_needed.*' => ['string', Rule::in($userServiceTypeValues)],
    ])->validate();

    $profilePatch = [];
    if (array_key_exists('number_of_children', $validated)) {
      $count = (int) $validated['number_of_children'];
      $profilePatch['number_of_children'] = $validated['number_of_children'];
      $profilePatch['has_children'] = $count > 0;
    }
    if (array_key_exists('children_ages_text', $validated)) {
      $profilePatch['children_ages_text'] = $validated['children_ages_text'];
      $ages = collect(preg_split('/\s*,\s*/', (string) $validated['children_ages_text']) ?: [])
        ->filter(fn ($age) => is_numeric($age))
        ->map(fn ($age) => (int) $age)
        ->filter(fn (int $age) => $age >= 0 && $age <= 25)
        ->values()
        ->all();
      if ($ages !== []) {
        $profilePatch['children_ages'] = $ages;
      }
    }
    if (array_key_exists('dogs_count', $validated)) {
      $count = (int) $validated['dogs_count'];
      $profilePatch['dogs_count'] = $validated['dogs_count'];
      $profilePatch['has_pets'] = $count > 0;
      if ($count > 0) {
        $profilePatch['pet_types'] = array_values(array_unique(array_merge(
          data_get($user->onboarding_data, 'profile.pet_types', []),
          ['dog']
        )));
      }
    }

    $onboardingData = $user->onboarding_data ?? [];
    $onboardingData['profile'] = array_merge($onboardingData['profile'] ?? [], $profilePatch);

    if (array_key_exists('services_needed', $validated) && is_array($validated['services_needed'])) {
      $onboardingData['services'] = array_merge($onboardingData['services'] ?? [], [
        'services_needed' => $validated['services_needed'],
      ]);
    }

    $onboardingData['role_accounts'] = array_merge($onboardingData['role_accounts'] ?? [], [
      'seeker' => ['status' => 'complete'],
    ]);

    RoleHelper::ensureExists(UserRole::USER->value);
    $user->assignRole(UserRole::USER->value);
    $user->update(['onboarding_data' => $onboardingData]);

    return $user->refresh();
  }

  public static function startProvider(User $user): User
  {
    if (! $user->hasRole(UserRole::USER->value)) {
      throw ValidationException::withMessages([
        'account' => 'You need a service seeker account before adding a provider profile.',
      ]);
    }

    if ($user->serviceProvider()->exists()) {
      throw ValidationException::withMessages([
        'account' => 'You already have a service provider account.',
      ]);
    }

    $onboardingData = $user->onboarding_data ?? [];
    $onboardingData['role_accounts'] = array_merge($onboardingData['role_accounts'] ?? [], [
      'provider' => ['status' => 'in_progress'],
    ]);
    $onboardingData['registration'] = array_merge($onboardingData['registration'] ?? [], [
      'role' => UserRole::PROVIDER->value,
    ]);

    $user->update(['onboarding_data' => $onboardingData]);

    return $user->refresh();
  }

  public static function markProviderAccountComplete(User $user): void
  {
    if (! $user->hasRole(UserRole::USER->value)) {
      return;
    }

    $onboardingData = $user->onboarding_data ?? [];
    $onboardingData['role_accounts'] = array_merge($onboardingData['role_accounts'] ?? [], [
      'provider' => ['status' => 'complete'],
    ]);

    $user->update(['onboarding_data' => $onboardingData]);
  }

  public static function dashboardRouteFor(User $user, ?string $sessionPortal = null): string
  {
    if ($user->isAdmin()) {
      return route('admin.dashboard');
    }

    if ($user->isAffiliate()) {
      return route('affiliate.dashboard');
    }

    if ($user->isAdvertiser() && ! $user->hasRole(UserRole::USER->value)) {
      return route('advertiser.dashboard');
    }

    $portal = self::resolvedActivePortal($user, $sessionPortal);

    return $portal === UserRole::PROVIDER->value
      ? route('provider.dashboard')
      : route('dashboard');
  }
}
