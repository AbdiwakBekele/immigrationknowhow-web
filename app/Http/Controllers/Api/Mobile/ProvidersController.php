<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ProviderResource;
use App\Models\ServiceProvider;
use App\Support\UserRoleAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class ProvidersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = Auth::guard('sanctum')->user();
        $viewerCountryRaw = $viewer?->country;
        $viewerCountry = is_string($viewerCountryRaw) ? trim($viewerCountryRaw) : '';
        $favoritesEnabled = Schema::hasTable('provider_favorites');
        $favoriteProviderIds = [];

        if ($favoritesEnabled && $viewer?->hasRole('user')) {
            $favoriteProviderIds = $viewer
                ->favoriteServiceProviders()
                ->pluck('service_providers.id')
                ->all();
        }

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state,country'])
            ->active()
            ->acceptingClients()
            ->whereUserCountry($viewerCountry !== '' ? $viewerCountry : null);

        if ($request->boolean('favorites')) {
            if ($favoritesEnabled && $viewer?->hasRole('user')) {
                $query->whereIn('service_providers.id', $favoriteProviderIds);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if ($request->filled('service_type')) {
            $query->byServiceType((string) $request->input('service_type'));
        }

        if ($request->filled('language')) {
            $query->byLanguage((string) $request->input('language'));
        }

        if ($request->filled('location')) {
            $location = (string) $request->input('location');
            $query->where(function ($q) use ($location) {
                $q->where('serves_remote', true)
                    ->orWhereHas('user', function ($uq) use ($location) {
                        $uq->where('city', 'like', "%{$location}%")
                            ->orWhere('state', 'like', "%{$location}%");
                    });
            });
        }

        if ($request->filled('search')) {
            $query->search((string) $request->input('search'));
        }

        if ($request->boolean('remote_only')) {
            $query->where('serves_remote', true);
        }

        if ($request->boolean('free_consultation')) {
            $query->where('free_consultation', true);
        }

        $sortBy = (string) $request->input('sort', 'rating');
        $query->when($sortBy === 'rating', fn ($q) => $q->orderByDesc('average_rating')->orderByDesc('total_reviews'))
            ->when($sortBy === 'reviews', fn ($q) => $q->orderByDesc('total_reviews'))
            ->when($sortBy === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->when($sortBy === 'experience', fn ($q) => $q->orderByDesc('years_experience'));
        $query->orderByDesc('is_featured');

        $perPage = max(1, min(50, (int) $request->integer('per_page', 20)));
        $providers = $query->paginate($perPage)->withQueryString();
        $favoriteSet = array_flip($favoriteProviderIds);

        $providers->setCollection(
            $providers->getCollection()->map(function (ServiceProvider $provider) use ($favoriteSet) {
                $provider->setAttribute('is_favorited', isset($favoriteSet[$provider->id]));

                return $provider;
            })
        );

        return $this->success('OK', [
            'providers' => ProviderResource::collection($providers)->response()->getData(true),
        ]);
    }

    public function show(Request $request, ServiceProvider $provider): JsonResponse
    {
        $viewer = Auth::guard('sanctum')->user();
        $isOwner = $viewer && $viewer->serviceProvider && (int) $viewer->serviceProvider->getKey() === (int) $provider->getKey();

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

        $canContact = $viewer
            && UserRoleAccounts::canContactServiceProviders($viewer, $request->header('X-Active-Portal'));
        $favoritesEnabled = Schema::hasTable('provider_favorites');
        $canFavorite = $favoritesEnabled && $viewer && $viewer->hasRole('user') && ! $isOwner;
        $isFavorited = $canFavorite
            && $viewer->favoriteServiceProviders()->whereKey($provider->getKey())->exists();

        $provider->setAttribute('is_favorited', $isFavorited);

        return $this->success('OK', [
            'provider' => (new ProviderResource($provider))->resolve(),
            'canContactProvider' => $canContact,
            'isOwnListingPreview' => $isOwner,
            'canFavoriteProvider' => $canFavorite,
            'isFavorited' => $isFavorited,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function success(string $message, array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
