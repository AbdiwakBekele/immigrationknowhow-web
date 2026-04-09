<?php

namespace App\Http\Controllers\Provider;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        return Inertia::render('Provider/Profile/Index', [
            'user' => $user->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'avatar',
            ]),
            'provider' => $provider ? $provider->only([
                'id',
                'business_name',
                'slug',
                'bio',
                'description',
                'tagline',
                'business_email',
                'business_phone',
                'website',
                'service_types',
                'specializations',
                'languages_offered',
                'pricing_model',
                'hourly_rate',
                'consultation_fee',
                'free_consultation',
                'pricing_notes',
                'serves_remote',
                'serves_in_person',
                'service_radius_miles',
                'service_areas',
                'license_number',
                'license_state',
                'license_expiry',
                'certifications',
                'years_experience',
                'linkedin_url',
                'facebook_url',
                'twitter_url',
                'instagram_url',
                'youtube_url',
                'tiktok_url',
                'verification_status',
                'accepting_clients',
                'average_rating',
                'total_reviews',
            ]) : null,
        ]);
    }

    public function edit(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        return Inertia::render('Provider/Profile/Edit', [
            'user' => $user->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'avatar',
            ]),
            'provider' => $provider ? $provider->only([
                'id',
                'business_name',
                'slug',
                'bio',
                'description',
                'tagline',
                'business_email',
                'business_phone',
                'website',
                'service_types',
                'specializations',
                'languages_offered',
                'pricing_model',
                'hourly_rate',
                'consultation_fee',
                'free_consultation',
                'pricing_notes',
                'serves_remote',
                'serves_in_person',
                'service_radius_miles',
                'service_areas',
                'license_number',
                'license_state',
                'license_expiry',
                'certifications',
                'years_experience',
                'linkedin_url',
                'facebook_url',
                'twitter_url',
                'instagram_url',
                'youtube_url',
                'tiktok_url',
                'verification_status',
                'accepting_clients',
            ]) : null,
            'serviceTypes' => collect(ServiceType::cases())->map(fn($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
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
            'business_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'service_types' => ['required', 'array', 'min:1'],
            'service_types.*' => [Rule::enum(ServiceType::class)],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:255'],
            'languages_offered' => ['required', 'array', 'min:1'],
            'languages_offered.*' => ['string', 'max:50'],
            'pricing_model' => ['nullable', 'in:hourly,flat_rate,consultation,custom'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'free_consultation' => ['boolean'],
            'pricing_notes' => ['nullable', 'string', 'max:1000'],
            'serves_remote' => ['boolean'],
            'serves_in_person' => ['boolean'],
            'service_radius_miles' => ['nullable', 'integer', 'min:1', 'max:500'],
            'service_areas' => ['nullable', 'array'],
            'service_areas.*' => ['string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_state' => ['nullable', 'string', 'max:100'],
            'license_expiry' => ['nullable', 'date'],
            'certifications' => ['nullable', 'array'],
            'certifications.*.name' => ['required', 'string', 'max:255'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:255'],
            'certifications.*.year' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:100'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'accepting_clients' => ['boolean'],
        ]);

        // Update slug if business name changed
        if ($validated['business_name'] !== $provider->business_name) {
            $validated['slug'] = Str::slug($validated['business_name']);
            
            // Ensure unique slug
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (ServiceProvider::where('slug', $validated['slug'])->where('id', '!=', $provider->id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $provider->update($validated);

        return redirect()
            ->route('provider.profile.index')
            ->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = auth()->user();

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return redirect()
            ->route('provider.profile.edit')
            ->with('success', 'Profile photo updated.');
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
