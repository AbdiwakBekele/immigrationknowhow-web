<?php

namespace App\Http\Resources\Mobile;

use App\Models\ServiceTypeOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $user->phone_verified_at,
            'country' => $user->country,
            'state' => $user->state,
            'city' => $user->city,
            'postal_code' => $user->postal_code,
            'preferred_language' => $user->preferred_language,
            'avatar_url' => $user->avatar_url,
            'onboarding_completed' => $this->resolveOnboardingCompletedForMobile($user),
            'roles' => method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->values()->all()
                : [],
            'requires_background_check' => $this->providerRequiresBackgroundCheck($user),
        ];
    }

    private function resolveOnboardingCompletedForMobile($user): bool
    {
        if (! $user->onboarding_completed) {
            return false;
        }

        if ($user->onboarding_completed_at !== null) {
            return true;
        }

        return $user->hasCompletedSignupAddressStep()
            && ($user->hasCompletedSignupPhoneStep() || $user->isAdmin() || $user->isAffiliate());
    }

    private function providerRequiresBackgroundCheck($user): bool
    {
        $provider = $user->serviceProvider;
        if (! $provider || ! is_array($provider->service_types) || $provider->service_types === []) {
            return false;
        }

        $providerTypes = collect($provider->service_types)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => $this->canonicalServiceTypeValue((string) $v))
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
                ->map(fn ($v) => $this->canonicalServiceTypeValue((string) $v))
                ->filter()
                ->unique();

            return $providerTypes->intersect($requiredTypes)->isNotEmpty();
        }

        return $providerTypes
            ->intersect(['pet_sitter', 'petsitter', 'babysitter', 'baby_sitter', 'tutor'])
            ->isNotEmpty();
    }

    private function canonicalServiceTypeValue(string $value): string
    {
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($value))), '_');

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
