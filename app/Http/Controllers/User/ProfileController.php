<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\LibraryUserAccess;
use App\Models\Message;
use App\Models\ServiceProvider;
use App\Notifications\RecommendedProviderInvitationNotification;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    private const SERVICE_LOCATION_OPTIONS = ['usa', 'uk', 'europe', 'canada', 'other'];

    public function edit(): Response
    {
        $user = auth()->user();
        $onboardingData = $user->onboarding_data ?? [];
        $profileData = data_get($onboardingData, 'profile', []);
        $languageOptions = LanguageOptions::selectOptions();
        $serviceTypeOptions = ServiceTypeOptions::userIntakeOptions();

        return Inertia::render('User/Profile/Edit', [
            'user' => array_merge(
                $user->only([
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'avatar',
                    'city',
                    'state',
                    'country',
                    'preferred_language',
                ]),
                [
                    'avatar_url' => $user->avatar_url,
                    'languages_spoken' => $user->languages ?? [],
                    'service_location' => data_get($profileData, 'service_location') ?? data_get($profileData, 'country_of_origin'),
                    'has_children' => (bool) data_get($profileData, 'has_children', false),
                    'children_ages' => data_get($profileData, 'children_ages', []),
                    'has_pets' => (bool) data_get($profileData, 'has_pets', false),
                    'pet_types' => data_get($profileData, 'pet_types', []),
                    'social_links' => data_get($profileData, 'social_links', []),
                    'hobbies' => data_get($profileData, 'hobbies', []),
                    'provider_invites' => data_get($profileData, 'provider_invites', []),
                    'notification_preferences' => array_merge(
                        $this->defaultNotificationPreferences(),
                        data_get($onboardingData, 'notification_preferences', [])
                    ),
                ]
            ),
            'countryOptions' => CountryOptions::selectOptions(),
            'languageOptions' => $languageOptions,
            'serviceTypeOptions' => $serviceTypeOptions,
            'profileStats' => [
                'completion' => $this->calculateProfileCompletion($user),
                'unread_messages' => Message::unreadIncomingCountFor($user),
                'purchased_products' => $this->purchasedProducts($user)->count(),
                'matched_providers' => $this->matchedProviders($user, $serviceTypeOptions, $languageOptions)->count(),
            ],
            'recentMessages' => $this->recentMessages($user),
            'purchasedProducts' => $this->purchasedProducts($user)->values(),
            'matchedProviders' => $this->matchedProviders($user, $serviceTypeOptions, $languageOptions)->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'languages_spoken' => ['nullable', 'array'],
            'languages_spoken.*' => ['string', 'max:50'],
            'service_location' => ['nullable', 'string', Rule::in(self::SERVICE_LOCATION_OPTIONS)],
            'has_children' => ['boolean'],
            'children_ages' => ['nullable', 'array', 'max:12'],
            'children_ages.*' => ['nullable', 'integer', 'min:0', 'max:25'],
            'has_pets' => ['boolean'],
            'pet_types' => ['nullable', 'array', 'max:12'],
            'pet_types.*' => ['nullable', 'string', 'max:60'],
            'social_links' => ['nullable', 'array', 'max:8'],
            'social_links.*.label' => ['nullable', 'string', 'max:60'],
            'social_links.*.url' => ['nullable', 'url', 'max:255'],
            'hobbies' => ['nullable', 'array', 'max:20'],
            'hobbies.*' => ['nullable', 'string', 'max:80'],
        ]);

        $onboardingData = $user->onboarding_data ?? [];
        data_set($onboardingData, 'profile.service_location', $validated['service_location'] ?? null);
        data_set($onboardingData, 'profile.country_of_origin', null);
        data_set($onboardingData, 'profile.immigration_status', null);
        data_set($onboardingData, 'profile.has_children', (bool) ($validated['has_children'] ?? false));
        data_set($onboardingData, 'profile.children_ages', $this->cleanChildAges($validated['children_ages'] ?? []));
        data_set($onboardingData, 'profile.has_pets', (bool) ($validated['has_pets'] ?? false));
        data_set($onboardingData, 'profile.pet_types', $this->cleanStringList($validated['pet_types'] ?? []));
        data_set($onboardingData, 'profile.social_links', $this->cleanSocialLinks($validated['social_links'] ?? []));
        data_set($onboardingData, 'profile.hobbies', $this->cleanStringList($validated['hobbies'] ?? []));

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'en',
            'languages' => $validated['languages_spoken'] ?? [],
            'onboarding_data' => $onboardingData,
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function storeProviderInvite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'service_type' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = auth()->user();
        $onboardingData = $user->onboarding_data ?? [];
        $invite = [
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'service_type' => $validated['service_type'] ?? null,
            'website' => $validated['website'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => 'invited',
            'created_at' => now()->toIso8601String(),
        ];

        $invites = data_get($onboardingData, 'profile.provider_invites', []);
        $invites[] = $invite;
        data_set($onboardingData, 'profile.provider_invites', $invites);

        $user->update(['onboarding_data' => $onboardingData]);

        Notification::route('mail', $invite['email'])
            ->notify(new RecommendedProviderInvitationNotification($invite, $user));

        return back()->with('success', 'Provider invitation sent.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $user = auth()->user();

        // Delete old avatar if exists
        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('success', 'Avatar updated successfully.');
    }

    public function deleteAvatar(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return back()->with('success', 'Avatar removed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_preferences' => ['required', 'array'],
            'notification_preferences.email_new_message' => ['boolean'],
            'notification_preferences.email_lead_update' => ['boolean'],
            'notification_preferences.email_review_received' => ['boolean'],
            'notification_preferences.email_marketing' => ['boolean'],
            'notification_preferences.push_enabled' => ['boolean'],
        ]);

        $user = auth()->user();
        $onboardingData = $user->onboarding_data ?? [];
        data_set($onboardingData, 'notification_preferences', array_merge(
            $this->defaultNotificationPreferences(),
            $validated['notification_preferences']
        ));

        $user->update([
            'onboarding_data' => $onboardingData,
        ]);

        return back()->with('success', 'Notification preferences updated.');
    }

    private function defaultNotificationPreferences(): array
    {
        return [
            'email_new_message' => true,
            'email_lead_update' => true,
            'email_review_received' => true,
            'email_marketing' => false,
            'push_enabled' => true,
        ];
    }

    private function recentMessages($user)
    {
        return Message::query()
            ->where('sender_id', '!=', $user->id)
            ->whereHas('conversation', fn ($query) => $query->forUser($user)->forServiceInquiries())
            ->with([
                'sender:id,first_name,last_name,avatar',
                'conversation:id,uuid,service_provider_id',
                'conversation.serviceProvider:id,slug,business_name,user_id',
                'conversation.serviceProvider.user:id,first_name,last_name,avatar',
            ])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Message $message) => [
                'uuid' => $message->uuid,
                'conversation_uuid' => $message->conversation?->uuid,
                'sender_name' => $message->sender?->full_name ?: 'Provider',
                'sender_avatar_url' => $message->sender?->avatar_url,
                'provider_name' => $message->conversation?->serviceProvider?->business_name,
                'body' => Str::limit($message->body, 140),
                'created_at' => optional($message->created_at)?->toIso8601String(),
                'is_unread' => ! $message->reads()->where('user_id', $user->id)->exists(),
            ]);
    }

    private function purchasedProducts($user)
    {
        return LibraryUserAccess::query()
            ->where('user_id', $user->id)
            ->whereNotNull('purchased_at')
            ->with(['libraryItem.libraryAuthor'])
            ->latest('purchased_at')
            ->limit(6)
            ->get()
            ->map(function (LibraryUserAccess $access) {
                $item = $access->libraryItem;
                if (! $item || ! $item->is_active) {
                    return null;
                }

                $progress = is_array($access->progress) ? $access->progress : [];

                return [
                    'access_id' => $access->id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'author' => $item->author,
                    'cover_image_url' => $item->cover_image_url,
                    'purchased_at' => optional($access->purchased_at)?->toIso8601String(),
                    'price' => $access->purchase_amount !== null ? (float) $access->purchase_amount : null,
                    'currency' => $access->purchase_currency ?: $item->currency,
                    'reading_progress' => (int) data_get($progress, 'reading.percentage', 0),
                    'audio_progress' => (int) data_get($progress, 'audio.percentage', 0),
                    'has_audio' => (bool) $item->audio_file_path,
                ];
            })
            ->filter();
    }

    private function matchedProviders($user, array $serviceTypeOptions, array $languageOptions)
    {
        $languageLabels = collect($languageOptions)->pluck('label', 'value');
        $serviceLabels = collect($serviceTypeOptions)->pluck('label', 'value');
        $desiredServiceTypes = $this->desiredServiceTypes($user, $serviceTypeOptions);
        $userLanguages = collect($user->languages ?? [])
            ->filter(fn ($language) => is_string($language) && trim($language) !== '')
            ->map(fn ($language) => trim($language))
            ->values();

        $providers = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state,country'])
            ->active()
            ->acceptingClients()
            ->verified()
            ->exceptOwnListing($user)
            ->whereUserCountry($user->country)
            ->when($user->state, function ($query) use ($user) {
                $query->where(function ($locationQuery) use ($user) {
                    $locationQuery->where('serves_remote', true)
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('state', $user->state));
                });
            })
            ->limit(24)
            ->get();

        return $providers
            ->map(function (ServiceProvider $provider) use ($desiredServiceTypes, $serviceLabels, $user, $userLanguages, $languageLabels) {
                $providerLanguages = collect($provider->languages_offered ?? [])->filter()->values();
                $providerServices = collect($provider->service_types ?? [])->filter()->values();
                $languageMatches = $userLanguages->intersect($providerLanguages)->values();
                $serviceMatches = $desiredServiceTypes->intersect($providerServices)->values();
                $sameState = $user->state && $provider->user?->state === $user->state;
                $remote = (bool) $provider->serves_remote;

                $score = 0;
                $reasons = [];

                if ($languageMatches->isNotEmpty()) {
                    $score += 40;
                    $reasons[] = 'Speaks '.$languageMatches
                        ->map(fn ($language) => $languageLabels[$language] ?? strtoupper($language))
                        ->join(', ');
                }

                if ($sameState) {
                    $score += 30;
                    $reasons[] = 'Near your location';
                } elseif ($remote) {
                    $score += 18;
                    $reasons[] = 'Available remotely';
                }

                if ($serviceMatches->isNotEmpty()) {
                    $score += 35;
                    $reasons[] = 'Matches '.$serviceMatches
                        ->map(fn ($service) => $serviceLabels[$service] ?? Str::of($service)->replace(['_', '-'], ' ')->title())
                        ->join(', ');
                }

                $score += min(10, (int) $provider->total_reviews);

                return [
                    'id' => $provider->id,
                    'slug' => $provider->slug,
                    'business_name' => $provider->business_name ?: $provider->user?->full_name,
                    'avatar_url' => $provider->user?->avatar_url,
                    'location' => $provider->location_display,
                    'service_types' => $providerServices
                        ->map(fn ($service) => $serviceLabels[$service] ?? Str::of($service)->replace(['_', '-'], ' ')->title()->toString())
                        ->values(),
                    'languages' => $providerLanguages
                        ->map(fn ($language) => $languageLabels[$language] ?? strtoupper((string) $language))
                        ->values(),
                    'average_rating' => $provider->average_rating !== null ? (float) $provider->average_rating : null,
                    'total_reviews' => (int) $provider->total_reviews,
                    'match_score' => $score,
                    'match_reasons' => $reasons,
                ];
            })
            ->filter(fn (array $provider) => $provider['match_score'] > 0)
            ->sortByDesc('match_score')
            ->take(6)
            ->values();
    }

    private function desiredServiceTypes($user, array $serviceTypeOptions)
    {
        $profileData = data_get($user->onboarding_data ?? [], 'profile', []);
        $serviceValues = collect(data_get($user->onboarding_data ?? [], 'services.services_needed', []));
        $serviceOptions = collect($serviceTypeOptions);

        if ((bool) data_get($profileData, 'has_pets', false)) {
            $serviceValues = $serviceValues->merge($this->serviceValuesContaining($serviceOptions, ['pet', 'animal', 'dog', 'cat']));
        }

        $youngChildren = collect(data_get($profileData, 'children_ages', []))
            ->filter(fn ($age) => is_numeric($age) && (int) $age <= 13)
            ->isNotEmpty();

        if ((bool) data_get($profileData, 'has_children', false) && $youngChildren) {
            $serviceValues = $serviceValues->merge($this->serviceValuesContaining($serviceOptions, ['baby', 'child', 'kid', 'sitter', 'daycare']));
        }

        return $serviceValues
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->unique()
            ->values();
    }

    private function serviceValuesContaining($serviceOptions, array $needles)
    {
        return $serviceOptions
            ->filter(function (array $option) use ($needles) {
                $haystack = Str::lower(($option['value'] ?? '').' '.($option['label'] ?? ''));

                foreach ($needles as $needle) {
                    if (Str::contains($haystack, $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('value');
    }

    private function calculateProfileCompletion($user): int
    {
        $profileData = data_get($user->onboarding_data ?? [], 'profile', []);
        $checks = [
            filled($user->first_name) && filled($user->last_name),
            filled($user->email),
            filled($user->phone),
            filled($user->city) || filled($user->state) || filled($user->country),
            ! empty($user->languages),
            filled($user->avatar),
            filled(data_get($profileData, 'service_location')) || filled(data_get($profileData, 'country_of_origin')),
            ! empty(data_get($profileData, 'social_links', [])),
            ! empty(data_get($profileData, 'hobbies', [])),
            (bool) data_get($profileData, 'has_children', false) || (bool) data_get($profileData, 'has_pets', false),
        ];

        return (int) round((collect($checks)->filter()->count() / count($checks)) * 100);
    }

    private function cleanChildAges(array $ages): array
    {
        return collect($ages)
            ->filter(fn ($age) => $age !== null && $age !== '')
            ->map(fn ($age) => (int) $age)
            ->filter(fn (int $age) => $age >= 0 && $age <= 25)
            ->unique()
            ->values()
            ->all();
    }

    private function cleanStringList(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => is_string($item) ? trim($item) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cleanSocialLinks(array $links): array
    {
        return collect($links)
            ->map(function (array $link) {
                $url = trim((string) ($link['url'] ?? ''));
                if ($url === '') {
                    return null;
                }

                $label = trim((string) ($link['label'] ?? ''));

                return [
                    'label' => $label !== '' ? $label : (parse_url($url, PHP_URL_HOST) ?: 'Social profile'),
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
