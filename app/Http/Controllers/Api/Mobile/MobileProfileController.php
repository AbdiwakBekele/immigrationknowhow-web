<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ProviderResource;
use App\Http\Resources\Mobile\UserResource;
use App\Models\ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MobileProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->serviceProvider?->loadMissing('user');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
                'provider' => $provider ? (new ProviderResource($provider))->resolve() : null,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
        ]);

        $country = strtoupper((string) ($validated['country'] ?? ''));
        $preferredLanguage = strtolower((string) ($validated['preferred_language'] ?? 'en')) ?: 'en';

        $onboardingData = $user->onboarding_data ?? [];
        $location = $onboardingData['location'] ?? [];
        $onboardingData['location'] = array_merge($location, [
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $country,
            'postal_code' => $validated['postal_code'] ?? null,
        ]);

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $country,
            'postal_code' => $validated['postal_code'] ?? null,
            'preferred_language' => $preferredLanguage,
            'onboarding_data' => $onboardingData,
        ]);

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
            ],
        ]);
    }

    public function updateProvider(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->serviceProvider;

        if (! $provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found. Complete onboarding.',
                'errors' => (object) [],
            ], 404);
        }

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:255'],
            'service_areas' => ['nullable', 'array'],
            'service_areas.*' => ['string', 'max:255'],
        ]);

        if ($validated['business_name'] !== $provider->business_name) {
            $validated['slug'] = Str::slug($validated['business_name']);

            $baseSlug = $validated['slug'];
            $counter = 1;
            while (ServiceProvider::where('slug', $validated['slug'])->where('id', '!=', $provider->id)->exists()) {
                $validated['slug'] = $baseSlug.'-'.$counter++;
            }
        }

        $provider->update([
            'business_name' => $validated['business_name'],
            'slug' => $validated['slug'] ?? $provider->slug,
            'tagline' => $validated['tagline'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'website' => $validated['website'] ?? null,
            'years_experience' => $validated['years_experience'] ?? null,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'specializations' => array_values($validated['specializations'] ?? []),
            'service_areas' => array_values($validated['service_areas'] ?? []),
        ]);

        $onboardingData = $user->onboarding_data ?? [];
        $onboardingData['business'] = array_merge($onboardingData['business'] ?? [], [
            'business_name' => $provider->business_name,
            'tagline' => $provider->tagline,
            'bio' => $provider->bio,
            'website' => $provider->website,
            'years_experience' => $provider->years_experience,
        ]);
        $onboardingData['pricing'] = array_merge($onboardingData['pricing'] ?? [], [
            'hourly_rate' => $provider->hourly_rate !== null ? (float) $provider->hourly_rate : null,
        ]);
        $onboardingData['services'] = array_merge($onboardingData['services'] ?? [], [
            'specializations' => $provider->specializations ?? [],
        ]);
        $onboardingData['service-area'] = array_merge($onboardingData['service-area'] ?? [], [
            'areas' => $provider->service_areas ?? [],
        ]);

        $user->update([
            'onboarding_data' => $onboardingData,
        ]);

        $user->refresh();
        $provider->refresh()->loadMissing('user');

        return response()->json([
            'success' => true,
            'message' => 'Provider profile updated successfully.',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
                'provider' => (new ProviderResource($provider))->resolve(),
            ],
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated.',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
            ],
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile photo removed.',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
            ],
        ]);
    }
}
