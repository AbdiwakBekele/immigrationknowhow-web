<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MobileProfileController extends Controller
{
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
