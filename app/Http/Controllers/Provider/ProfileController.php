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
                'primary_service_type',
                'bio',
                'credentials',
                'services_offered',
                'languages_spoken',
                'years_experience',
                'price_range_min',
                'price_range_max',
                'offers_free_consultation',
                'offers_remote',
                'city',
                'state',
                'country',
                'service_areas',
                'phone',
                'show_phone',
                'website_url',
                'linkedin_url',
                'response_time',
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
            'primary_service_type' => ['required', Rule::enum(ServiceType::class)],
            'bio' => ['nullable', 'string', 'max:2000'],
            'credentials' => ['nullable', 'array'],
            'credentials.*.title' => ['required', 'string', 'max:255'],
            'credentials.*.issuer' => ['nullable', 'string', 'max:255'],
            'credentials.*.year' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
            'services_offered' => ['nullable', 'array'],
            'services_offered.*' => ['string', 'max:255'],
            'languages_spoken' => ['nullable', 'array'],
            'languages_spoken.*' => ['string', 'max:50'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:100'],
            'price_range_min' => ['nullable', 'numeric', 'min:0'],
            'price_range_max' => ['nullable', 'numeric', 'min:0', 'gte:price_range_min'],
            'offers_free_consultation' => ['boolean'],
            'offers_remote' => ['boolean'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'service_areas' => ['nullable', 'array'],
            'service_areas.*' => ['string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'show_phone' => ['boolean'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'response_time' => ['nullable', 'string', 'max:100'],
        ]);

        // Update slug if business name changed
        if ($validated['business_name'] !== $provider->business_name) {
            $validated['slug'] = Str::slug($validated['business_name']);
            
            // Ensure unique slug
            $baseSlug = $validated['slug'];
            $counter = 1;
            while ($provider->where('slug', $validated['slug'])->where('id', '!=', $provider->id)->exists()) {
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

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

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
