<?php

namespace App\Http\Controllers\Provider;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        return Inertia::render('Provider/Profile/Edit', [
            'provider' => $provider,
            'serviceTypes' => collect(ServiceType::cases())->map(fn($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->toArray(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        if (!$provider) {
            return back()->withErrors(['error' => 'Provider profile not found.']);
        }

        $validated = $request->validate([
            // Business Info
            'business_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:100'],
            'bio' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            
            // Contact
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            
            // Services
            'service_types' => ['required', 'array', 'min:1'],
            'service_types.*' => [Rule::enum(ServiceType::class)],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:100'],
            'languages_offered' => ['required', 'array', 'min:1'],
            'languages_offered.*' => ['string', 'max:10'],
            
            // Pricing
            'pricing_model' => ['nullable', 'string', 'in:hourly,flat_rate,consultation,custom'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'free_consultation' => ['boolean'],
            'pricing_notes' => ['nullable', 'string', 'max:500'],
            
            // Service Area
            'serves_remote' => ['boolean'],
            'serves_in_person' => ['boolean'],
            'service_radius_miles' => ['nullable', 'integer', 'min:1', 'max:500'],
            'service_areas' => ['nullable', 'array'],
            'service_areas.*' => ['string', 'max:100'],
            
            // Credentials
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_state' => ['nullable', 'string', 'max:50'],
            'license_expiry' => ['nullable', 'date'],
            'certifications' => ['nullable', 'array'],
            'certifications.*.name' => ['required_with:certifications', 'string', 'max:255'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:255'],
            'certifications.*.year' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
            
            // Social Links
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            
            // Status
            'accepting_clients' => ['boolean'],
        ]);

        // Update slug if business name changed
        if ($validated['business_name'] !== $provider->business_name) {
            $validated['slug'] = Str::slug($validated['business_name']);
            
            // Ensure unique slug
            $baseSlug = $validated['slug'];
            $counter = 1;
            while ($provider->newQuery()->where('slug', $validated['slug'])->where('id', '!=', $provider->id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $provider->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = auth()->user();

        // Delete old avatar if exists and is stored locally
        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => Storage::disk('public')->url($path)]);

        return back()->with('success', 'Profile photo updated.');
    }

    public function updateCoverImage(Request $request): RedirectResponse
    {
        $request->validate([
            'cover_image' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        $provider = auth()->user()->serviceProvider;

        if ($provider->cover_image) {
            Storage::disk('public')->delete($provider->cover_image);
        }

        $path = $request->file('cover_image')->store('covers', 'public');
        $provider->update(['cover_image' => $path]);

        return back()->with('success', 'Cover image updated.');
    }

    public function preview(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider->load('reviews.user');

        return Inertia::render('Provider/Profile/Preview', [
            'provider' => $provider,
            'reviews' => $provider->reviews()->with('user')->latest()->paginate(5),
        ]);
    }
}
