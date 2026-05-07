<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\Message;
use App\Models\ServiceProvider;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        // Calculate stats
        $stats = [
            'totalLeads' => Lead::where('user_id', $user->id)->count(),
            'unreadMessages' => $this->getUnreadMessageCount($user),
            'profileCompletion' => $this->calculateProfileCompletion($user),
        ];

        // Recent leads with provider info
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

        // Recent messages
        $recentMessages = Message::whereHas('conversation', function ($q) use ($user) {
            $q->forUser($user)->forServiceInquiries();
        })
            ->with(['sender:id,first_name,last_name,avatar', 'conversation:id,uuid'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($message) {
                return [
                    'uuid' => $message->uuid,
                    'conversation_uuid' => $message->conversation->uuid,
                    'sender' => $message->sender,
                    'body' => $message->body,
                    'created_at' => $message->created_at,
                ];
            });

        // Recommended providers based on user's interests
        $recommendedProviders = $this->getRecommendedProviders($user);

        // Featured library items
        $libraryItems = LibraryItem::active()
            ->featured()
            ->with('libraryAuthor')
            ->limit(3)
            ->get([
                'uuid',
                'slug',
                'title',
                'author_id',
                'type',
                'cover_image',
                'is_premium',
                'price',
                'currency',
            ]);

        // Buyer's recent library purchases (load full item so price/currency always serialize for the UI)
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
                    'purchased_at' => $access->purchased_at,
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

        return Inertia::render('User/Dashboard', [
            'stats' => $stats,
            'recentLeads' => $recentLeads,
            'recentMessages' => $recentMessages,
            'recommendedProviders' => $recommendedProviders,
            'libraryItems' => $libraryItems,
            'purchasedItems' => $purchasedItems,
        ]);
    }

    protected function getUnreadMessageCount($user): int
    {
        return Message::unreadIncomingCountFor($user);
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
            ->whereUserCountry($user->country);

        // State-level discovery: show providers in the same state.
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

                // Weighted score: interests > language > proximity > quality.
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
