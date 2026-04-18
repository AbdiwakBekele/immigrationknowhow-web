<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LibraryUserAccess;
use App\Models\Message;
use App\Models\ProviderProfilePost;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\ProviderShareMeta;
use App\Support\ServiceTypeOptions;
use App\Support\UsStateOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        $profileFeed = $provider
            ? $provider->profilePosts()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (ProviderProfilePost $post) => $post->toFeedPayload())
                ->values()
                ->all()
            : [];

        $profileStats = [
            'completion' => $provider ? $this->providerListingCompletion($provider, $user) : 0,
            'unread_messages' => Message::unreadIncomingCountFor($user),
            'purchased_products' => LibraryUserAccess::query()->where('user_id', $user->id)->count(),
            'matched_providers' => $provider
                ? Lead::query()->where('service_provider_id', $provider->id)->count()
                : 0,
        ];

        return Inertia::render('Provider/Profile/Index', [
            'user' => array_merge($user->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'avatar',
                'created_at',
                'city',
                'state',
                'country',
                'preferred_language',
            ]), ['avatar_url' => $user->avatar_url]),
            'profileFeed' => $profileFeed,
            'profileStats' => $profileStats,
            'languageOptions' => LanguageOptions::selectOptions(),
            'countryOptions' => CountryOptions::selectOptions(),
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
                'health_certificates',
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

    private function providerListingCompletion(ServiceProvider $provider, User $user): int
    {
        $score = 0;
        if ($provider->business_name) {
            $score += 14;
        }
        if ($provider->tagline) {
            $score += 9;
        }
        if ($provider->bio) {
            $score += 9;
        }
        if ($provider->description) {
            $score += 9;
        }
        if ($provider->business_email) {
            $score += 9;
        }
        if ($provider->business_phone) {
            $score += 9;
        }
        if ($provider->website) {
            $score += 5;
        }
        if ($provider->service_types && count($provider->service_types)) {
            $score += 12;
        }
        if ($provider->languages_offered && count($provider->languages_offered)) {
            $score += 12;
        }
        if ($provider->service_areas && count($provider->service_areas)) {
            $score += 7;
        }
        if ($user->avatar) {
            $score += 5;
        }

        return min(100, $score);
    }

    public function edit(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        $profileFeed = $provider
            ? $provider->profilePosts()
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (ProviderProfilePost $post) => $post->toFeedPayload())
                ->values()
                ->all()
            : [];

        $profileStats = [
            'completion' => $provider ? $this->providerListingCompletion($provider, $user) : 0,
            'unread_messages' => Message::unreadIncomingCountFor($user),
            'purchased_products' => LibraryUserAccess::query()->where('user_id', $user->id)->count(),
            'matched_providers' => $provider
                ? Lead::query()->where('service_provider_id', $provider->id)->count()
                : 0,
        ];

        return Inertia::render('Provider/Profile/Edit', [
            'user' => array_merge($user->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'avatar',
                'created_at',
                'city',
                'state',
                'country',
                'preferred_language',
            ]), ['avatar_url' => $user->avatar_url]),
            'profileStats' => $profileStats,
            'languageOptions' => LanguageOptions::selectOptions(),
            'providerShare' => $provider ? ProviderShareMeta::forProvider($provider) : null,
            'profileFeed' => $profileFeed,
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
                'health_certificates',
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
            'serviceTypes' => ServiceTypeOptions::selectOptions('provider'),
            'countryOptions' => CountryOptions::selectOptions(),
            'stateOptions' => UsStateOptions::selectOptions($user->country ?? 'US'),
            'defaultLocationCountry' => $user->country ?? 'US',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        if (! $provider) {
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
            'service_types.*' => ['string', 'max:120'],
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
            'service_areas.*' => ['string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_state' => ['nullable', 'string', 'max:100'],
            'license_expiry' => ['nullable', 'date'],
            'certifications' => ['nullable', 'array'],
            'certifications.*.name' => ['required', 'string', 'max:255'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:255'],
            'certifications.*.year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')],
            'health_certificates' => ['nullable', 'array'],
            'health_certificates.*.name' => ['required', 'string', 'max:255'],
            'health_certificates.*.issuing_authority' => ['nullable', 'string', 'max:255'],
            'health_certificates.*.expiration_date' => ['nullable', 'date'],
            'health_certificates.*.file_path' => ['nullable', 'string', 'max:2048'],
            'health_certificates.*.original_name' => ['nullable', 'string', 'max:255'],
            'health_certificates.*.document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
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
                $validated['slug'] = $baseSlug.'-'.$counter++;
            }
        }

        $typesRequiringCertificates = collect(ServiceTypeOptions::selectOptions('provider'))
            ->filter(fn (array $type) => (bool) ($type['include_certificate'] ?? false))
            ->pluck('value')
            ->values();
        $hasCertificateEnabledType = collect($validated['service_types'] ?? [])
            ->intersect($typesRequiringCertificates)
            ->isNotEmpty();

        $existingCertificatePaths = collect($provider->health_certificates ?? [])
            ->pluck('file_path')
            ->filter()
            ->values();
        $keptCertificatePaths = collect();

        $validated['health_certificates'] = collect($validated['health_certificates'] ?? [])
            ->map(function (array $certificate) use (&$keptCertificatePaths) {
                $filePath = $certificate['file_path'] ?? null;
                $originalName = $certificate['original_name'] ?? null;

                if (isset($certificate['document']) && $certificate['document']) {
                    $filePath = $certificate['document']->store('provider-certificates', 'public');
                    $originalName = $certificate['document']->getClientOriginalName();
                }

                if ($filePath) {
                    $keptCertificatePaths->push($filePath);
                }

                return [
                    'name' => $certificate['name'],
                    'issuing_authority' => $certificate['issuing_authority'] ?? null,
                    'expiration_date' => $certificate['expiration_date'] ?? null,
                    'file_path' => $filePath,
                    'original_name' => $originalName,
                ];
            })
            ->values()
            ->all();

        if (! $hasCertificateEnabledType) {
            $validated['health_certificates'] = [];
            $pathsToDelete = $existingCertificatePaths;
        } else {
            $pathsToDelete = $existingCertificatePaths->diff($keptCertificatePaths);
        }
        foreach ($pathsToDelete as $path) {
            if (is_string($path) && $path !== '' && ! str_starts_with($path, 'http')) {
                Storage::disk('public')->delete($path);
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
            'avatar' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp,bmp', 'max:5120'],
        ]);

        $user = auth()->user();

        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return redirect()
            ->route('provider.profile.index')
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
