<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Support\ProviderShareMeta;
use App\Support\PublicSocialPreview;
use App\Support\UserRoleAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    protected array $languages = [
        'en' => 'English',
        'es' => 'Spanish',
        'zh' => 'Chinese (Mandarin)',
        'hi' => 'Hindi',
        'ar' => 'Arabic',
        'pt' => 'Portuguese',
        'fr' => 'French',
        'de' => 'German',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'vi' => 'Vietnamese',
        'tl' => 'Tagalog',
        'ru' => 'Russian',
    ];

    public function index(Request $request): Response
    {
        $viewerCountryRaw = $request->user()?->country;
        $viewerCountry = is_string($viewerCountryRaw) ? trim($viewerCountryRaw) : '';
        $favoritesEnabled = $this->favoritesEnabled();

        $favoriteProviderIds = [];
        if ($favoritesEnabled && $request->user()?->hasRole('user')) {
            $favoriteProviderIds = $request->user()
                ->favoriteServiceProviders()
                ->pluck('service_providers.id')
                ->all();
        }

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state,country'])
            ->active()
            ->acceptingClients()
            ->exceptOwnListing($request->user())
            ->whereUserCountry($viewerCountry !== '' ? $viewerCountry : null);

        if ($request->boolean('favorites')) {
            if ($favoritesEnabled && $request->user()?->hasRole('user')) {
                $query->whereIn('service_providers.id', $favoriteProviderIds);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        // Apply filters
        if ($request->filled('service_type')) {
            $query->byServiceType($request->input('service_type'));
        }

        if ($request->filled('language')) {
            $query->byLanguage($request->input('language'));
        }

        if ($request->filled('location')) {
            $location = $request->input('location');
            $query->where(function ($q) use ($location) {
                $q->where('serves_remote', true)
                    ->orWhereHas('user', function ($uq) use ($location) {
                        $uq->where('city', 'like', "%{$location}%")
                            ->orWhere('state', 'like', "%{$location}%");
                    });
            });
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('remote_only') && $request->boolean('remote_only')) {
            $query->where('serves_remote', true);
        }

        if ($request->filled('free_consultation') && $request->boolean('free_consultation')) {
            $query->where('free_consultation', true);
        }

        // Sorting
        $sortBy = $request->input('sort', 'rating');
        $query->when($sortBy === 'rating', fn ($q) => $q->orderByDesc('average_rating')->orderByDesc('total_reviews'))
            ->when($sortBy === 'reviews', fn ($q) => $q->orderByDesc('total_reviews'))
            ->when($sortBy === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->when($sortBy === 'experience', fn ($q) => $q->orderByDesc('years_experience'));

        // Featured providers first
        $query->orderByDesc('is_featured');

        $providers = $query->paginate(12)->withQueryString();

        $favoriteSet = array_flip($favoriteProviderIds);
        $providers->setCollection(
            $providers->getCollection()->map(function (ServiceProvider $p) use ($favoriteSet) {
                $p->append('primary_service_type');
                $p->setAttribute('is_favorited', isset($favoriteSet[$p->id]));

                return $p;
            })
        );

        $featuredProviders = $this->getFeaturedProviders($viewerCountry !== '' ? $viewerCountry : null, $request->user());
        if ($favoritesEnabled && $request->boolean('favorites') && $request->user()?->hasRole('user')) {
            $featuredProviders = $featuredProviders
                ->filter(fn (ServiceProvider $p) => isset($favoriteSet[$p->id]))
                ->values();
        }

        return Inertia::render('Marketplace/Index', [
            'providers' => $providers,
            'filters' => $request->only(['service_type', 'language', 'location', 'search', 'remote_only', 'free_consultation', 'sort', 'favorites']),
            'serviceTypes' => ServiceType::options(),
            'languages' => $this->languages,
            'featuredProviders' => $featuredProviders,
        ]);
    }

    public function show(Request $request, ServiceProvider $provider): Response
    {
        $viewer = $request->user();
        $isOwner = $viewer
            && $viewer->serviceProvider
            && (int) $viewer->serviceProvider->getKey() === (int) $provider->getKey();

        // Public visitors only see active listings; providers can preview their own (e.g. from Edit Profile)
        abort_unless($provider->is_active || $isOwner, 404);

        $relations = [
            'user:id,first_name,last_name,avatar,city,state,country',
            'reviews' => fn ($q) => $q->approved()->with('user:id,first_name,last_name,avatar')->latest()->limit(10),
        ];
        if (Schema::hasTable('provider_profile_posts')) {
            $relations['profilePosts'] = fn ($q) => $q->latest()->limit(50);
        }
        $provider->load($relations);

        if ($viewer && ! $isOwner) {
            $viewerCountry = is_string($viewer->country) ? trim($viewer->country) : '';
            $providerCountry = is_string($provider->user?->country) ? trim((string) $provider->user->country) : '';
            if ($viewerCountry !== '' && $providerCountry !== '' && $viewerCountry !== $providerCountry) {
                abort(404);
            }
        }

        if (! $isOwner) {
            $provider->incrementProfileViews();
        }

        // Get similar providers
        $similarProviders = $this->getSimilarProviders($provider, $viewer)->map(
            fn (ServiceProvider $p) => $p->append('primary_service_type')
        );

        $profileFeed = collect();
        if (Schema::hasTable('provider_profile_posts') && $provider->relationLoaded('profilePosts')) {
            $profileFeed = $provider->profilePosts
                ->map(fn ($post) => $post->toFeedPayload())
                ->values();
            $provider->unsetRelation('profilePosts');
        }

        $favoritesEnabled = $this->favoritesEnabled();
        $canFavorite = $favoritesEnabled && $viewer && $viewer->hasRole('user') && ! $isOwner;
        $isFavorited = $canFavorite
            && $viewer->favoriteServiceProviders()->whereKey($provider->getKey())->exists();

        $providerShare = ProviderShareMeta::forProvider($provider);
        PublicSocialPreview::apply($providerShare);

        return Inertia::render('Marketplace/Show', [
            'provider' => $provider,
            'profileFeed' => $profileFeed->all(),
            'similarProviders' => $similarProviders,
            'canContactProvider' => auth()->check() && UserRoleAccounts::canContactServiceProviders(
                auth()->user(),
                $request->session()->get(UserRoleAccounts::SESSION_ACTIVE_PORTAL)
            ),
            'isOwnListingPreview' => $isOwner,
            'canFavorite' => $canFavorite,
            'isFavorited' => $isFavorited,
            'serviceTypeLabels' => collect($provider->service_types ?? [])
                ->map(fn ($type) => ServiceType::tryFrom($type)?->label() ?? $type)
                ->toArray(),
            'providerShare' => $providerShare,
        ]);
    }

    protected function getFeaturedProviders(?string $viewerCountry = null, ?User $viewer = null)
    {
        return ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state,country'])
            ->active()
            ->acceptingClients()
            ->verified()
            ->featured()
            ->exceptOwnListing($viewer)
            ->whereUserCountry($viewerCountry)
            ->limit(6)
            ->get()
            ->map(fn (ServiceProvider $p) => $p->append('primary_service_type'));
    }

    protected function getSimilarProviders(ServiceProvider $provider, ?User $viewer = null)
    {
        $provider->loadMissing('user:id,country');

        return ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state,country'])
            ->active()
            ->acceptingClients()
            ->verified()
            ->exceptOwnListing($viewer)
            ->whereUserCountry($provider->user?->country)
            ->where('id', '!=', $provider->id)
            ->where(function ($q) use ($provider) {
                foreach ($provider->service_types as $type) {
                    $q->orWhereJsonContains('service_types', $type);
                }
            })
            ->orderByDesc('average_rating')
            ->limit(4)
            ->get()
            ->map(fn (ServiceProvider $p) => $p->append('primary_service_type'));
    }

    private function favoritesEnabled(): bool
    {
        return Schema::hasTable('provider_favorites');
    }
}
