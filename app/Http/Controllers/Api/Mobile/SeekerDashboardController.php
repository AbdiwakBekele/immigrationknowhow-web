<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ConversationResource;
use App\Http\Resources\Mobile\ProviderResource;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\Message;
use App\Models\ServiceProvider;
use App\Support\LanguageOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SeekerDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $favoritesEnabled = Schema::hasTable('provider_favorites');
        $favoriteProviderIds = $favoritesEnabled
            ? $user->favoriteServiceProviders()->pluck('service_providers.id')->all()
            : [];
        $favoriteSet = array_flip($favoriteProviderIds);

        $preferredLanguage = strtolower((string) ($user->preferred_language ?? ''));
        $languageLabels = LanguageOptions::labels();

        $stats = [
            'totalLeads' => Lead::where('user_id', $user->id)->count(),
            'unreadMessages' => Message::unreadIncomingCountFor($user),
            'profileCompletion' => $this->calculateProfileCompletion($user),
            'preferredLanguageLabel' => $preferredLanguage !== ''
                ? ($languageLabels[$preferredLanguage] ?? ucfirst($preferredLanguage))
                : null,
        ];

        $recentLeads = Lead::where('user_id', $user->id)
            ->whereHas('conversation', function ($q) use ($user) {
                $q->forUser($user)->forServiceInquiries();
            })
            ->with([
                'serviceProvider:id,slug,business_name',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'conversation:id,lead_id,uuid',
            ])
            ->latest()
            ->limit(5)
            ->get();

        $recentMessages = Conversation::query()
            ->forUser($user)
            ->forServiceInquiries()
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage',
                'lead:id,uuid,service_type,status',
            ])
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();

        $recommendedProviders = $this->getRecommendedProviders($user)
            ->map(function (ServiceProvider $provider) use ($favoriteSet) {
                $provider->setAttribute('is_favorited', isset($favoriteSet[$provider->id]));

                return $provider;
            });

        $savedProviders = collect();
        if ($favoritesEnabled) {
            $savedProviders = $user->favoriteServiceProviders()
                ->with(['user:id,first_name,last_name,avatar,city,state,country'])
                ->active()
                ->acceptingClients()
                ->exceptOwnListing($user)
                ->whereUserCountry($user->country)
                ->limit(6)
                ->get()
                ->map(function (ServiceProvider $provider) {
                    $provider->setAttribute('is_favorited', true);

                    return $provider;
                })
                ->values();
        }

        $libraryItems = LibraryItem::active()
            ->featured()
            ->with('libraryAuthor')
            ->limit(3)
            ->get()
            ->map(fn (LibraryItem $item) => [
                'uuid' => $item->uuid,
                'slug' => $item->slug,
                'title' => $item->title,
                'author' => $item->author,
                'type' => $item->type,
                'cover_image_url' => $item->cover_image_url,
                'is_premium' => $item->is_premium,
                'price' => $item->price !== null ? (float) $item->price : null,
                'currency' => $item->currency,
            ])
            ->values();

        $purchasedItems = LibraryUserAccess::query()
            ->where('user_id', $user->id)
            ->whereNotNull('purchased_at')
            ->with(['libraryItem.libraryAuthor'])
            ->latest('purchased_at')
            ->limit(5)
            ->get()
            ->map(function (LibraryUserAccess $access) {
                $item = $access->libraryItem;
                if (! $item || ! $item->is_active) {
                    return null;
                }

                return [
                    'access_id' => $access->id,
                    'purchased_at' => optional($access->purchased_at)->toIso8601String(),
                    'purchase_amount' => $access->purchase_amount,
                    'purchase_currency' => $access->purchase_currency,
                    'item' => [
                        'uuid' => $item->uuid,
                        'slug' => $item->slug,
                        'title' => $item->title,
                        'author' => $item->author,
                        'type' => $item->type,
                        'cover_image_url' => $item->cover_image_url,
                        'is_premium' => $item->is_premium,
                        'price' => $item->price !== null ? (float) $item->price : null,
                        'currency' => $item->currency,
                    ],
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'stats' => $stats,
                'recent_leads' => $recentLeads,
                'recent_messages' => ConversationResource::collection($recentMessages)->resolve(),
                'recommended_providers' => ProviderResource::collection($recommendedProviders)->resolve(),
                'saved_providers' => ProviderResource::collection($savedProviders)->resolve(),
                'library_items' => $libraryItems,
                'purchased_items' => $purchasedItems,
            ],
        ]);
    }

    protected function calculateProfileCompletion($user): int
    {
        $fields = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'city',
            'state',
            'country',
            'languages',
            'avatar',
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (! empty($user->$field)) {
                $completed++;
            }
        }

        return (int) round(($completed / count($fields)) * 100);
    }

    protected function getRecommendedProviders($user)
    {
        $serviceTypes = $this->userInterestedServiceTypes($user);
        $userLanguages = $this->userPreferredLanguages($user);
        $userState = is_string($user->state) ? trim($user->state) : '';
        $userCity = is_string($user->city) ? trim($user->city) : '';

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state,country'])
            ->active()
            ->acceptingClients()
            ->verified()
            ->exceptOwnListing($user)
            ->whereUserCountry($user->country);

        if ($userState !== '') {
            $query->whereHas('user', fn ($uq) => $uq->where('state', $userState));
        }

        return $query
            ->limit(120)
            ->get()
            ->map(function (ServiceProvider $provider) use ($serviceTypes, $userLanguages, $userCity) {
                $providerServiceTypes = collect($provider->service_types ?? [])
                    ->map(fn ($type) => $this->normalizeMatchValue((string) $type))
                    ->filter()
                    ->unique()
                    ->values();
                $providerLanguages = collect($provider->languages_offered ?? [])
                    ->map(fn ($language) => $this->normalizeMatchValue((string) $language))
                    ->filter()
                    ->unique()
                    ->values();

                $serviceMatchCount = $providerServiceTypes->intersect($serviceTypes)->count();
                $languageMatchCount = $providerLanguages->intersect($userLanguages)->count();

                $providerCity = $this->normalizeMatchValue((string) ($provider->user?->city ?? ''));
                $normalizedUserCity = $this->normalizeMatchValue($userCity);
                $sameCity = $normalizedUserCity !== '' && $providerCity !== '' && $providerCity === $normalizedUserCity;

                $score = ($serviceMatchCount * 35)
                    + ($languageMatchCount * 20)
                    + ($sameCity ? 18 : 0)
                    + ($provider->serves_in_person ? 8 : 0)
                    + ($provider->serves_remote ? 4 : 0)
                    + min(10, (float) ($provider->average_rating ?? 0));

                $provider->setAttribute('match_score', $score);

                return $provider;
            })
            ->sortByDesc(fn (ServiceProvider $provider) => [
                (float) ($provider->match_score ?? 0),
                (int) $provider->is_featured,
                (float) ($provider->average_rating ?? 0),
                (int) ($provider->total_reviews ?? 0),
            ])
            ->take(8)
            ->values();
    }

    private function userInterestedServiceTypes($user): Collection
    {
        $fromOnboarding = collect($user->onboarding_data['services']['types'] ?? []);
        $fromLeads = Lead::where('user_id', $user->id)
            ->distinct()
            ->pluck('service_type');

        return $fromOnboarding
            ->merge($fromLeads)
            ->map(fn ($type) => $this->normalizeMatchValue((string) $type))
            ->filter()
            ->unique()
            ->values();
    }

    private function userPreferredLanguages($user): Collection
    {
        return collect($user->languages ?? [])
            ->push($user->preferred_language)
            ->map(fn ($language) => $this->normalizeMatchValue((string) $language))
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeMatchValue(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($value))), '_');
    }
}
