<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
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
        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state'])
            ->active()
            ->acceptingClients();

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
        $providers->setCollection(
            $providers->getCollection()->map(
                fn (ServiceProvider $p) => $p->append('primary_service_type')
            )
        );

        return Inertia::render('Marketplace/Index', [
            'providers' => $providers,
            'filters' => $request->only(['service_type', 'language', 'location', 'search', 'remote_only', 'free_consultation', 'sort']),
            'serviceTypes' => ServiceType::options(),
            'languages' => $this->languages,
            'featuredProviders' => $this->getFeaturedProviders(),
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

        $provider->load([
            'user:id,first_name,last_name,avatar,city,state,country',
            'reviews' => fn ($q) => $q->approved()->with('user:id,first_name,last_name,avatar')->latest()->limit(10),
        ]);

        if (! $isOwner) {
            $provider->incrementProfileViews();
        }

        // Get similar providers
        $similarProviders = $this->getSimilarProviders($provider)->map(
            fn (ServiceProvider $p) => $p->append('primary_service_type')
        );

        return Inertia::render('Marketplace/Show', [
            'provider' => $provider,
            'similarProviders' => $similarProviders,
            'canContactProvider' => auth()->check() && ! auth()->user()->isProvider(),
            'isOwnListingPreview' => $isOwner,
            'serviceTypeLabels' => collect($provider->service_types ?? [])
                ->map(fn ($type) => ServiceType::tryFrom($type)?->label() ?? $type)
                ->toArray(),
        ]);
    }

    protected function getFeaturedProviders()
    {
        return ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state'])
            ->active()
            ->acceptingClients()
            ->verified()
            ->featured()
            ->limit(6)
            ->get()
            ->map(fn (ServiceProvider $p) => $p->append('primary_service_type'));
    }

    protected function getSimilarProviders(ServiceProvider $provider)
    {
        return ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state'])
            ->active()
            ->acceptingClients()
            ->verified()
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
}
