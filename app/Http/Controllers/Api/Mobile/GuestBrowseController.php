<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\GuestLibraryItemResource;
use App\Http\Resources\Mobile\GuestProviderResource;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\ServiceProvider;
use App\Support\LanguageOptions;
use App\Support\MarketplaceProviderFilters;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestBrowseController extends Controller
{
    public function meta(): JsonResponse
    {
        return $this->success('OK', [
            'service_types' => ServiceTypeOptions::selectOptions('user'),
            'language_options' => LanguageOptions::selectOptions(),
            'library_categories' => LibraryCategory::active()->ordered()->get(['id', 'name', 'slug']),
            'library_regions' => LibraryItem::regionOptions(),
        ]);
    }

    public function howItWorks(): JsonResponse
    {
        return $this->success('OK', [
            'title' => 'How Immigration Know How works',
            'steps' => [
                [
                    'title' => 'Browse service providers and resources',
                    'description' => 'Explore immigration service categories, trusted service providers, and educational eBooks without creating an account.',
                ],
                [
                    'title' => 'Create your free account',
                    'description' => 'Sign up to request a service provider match, save favorites, message service providers, and access your library.',
                ],
                [
                    'title' => 'Get matched with the right help',
                    'description' => 'Tell us what you need and connect with vetted immigration professionals in your area or remotely.',
                ],
                [
                    'title' => 'Learn and stay informed',
                    'description' => 'Purchase or access eBooks, track your cases, and use tools designed for your immigration journey.',
                ],
            ],
        ]);
    }

    public function libraryBrowse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(LibraryItem::supportedTypes())],
            'category' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', Rule::in(LibraryItem::supportedRegions())],
        ]);

        $query = LibraryItem::query()
            ->with(['category:id,name,slug'])
            ->active()
            ->select([
                'id',
                'title',
                'slug',
                'cover_image',
                'category_id',
                'regions',
            ]);

        if (! empty($validated['region'])) {
            $query->availableInRegion($validated['region']);
        }

        if (! empty($validated['search'])) {
            $query->search($validated['search']);
        }

        if (! empty($validated['category'])) {
            $category = LibraryCategory::where('slug', $validated['category'])->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $query->orderByDesc('is_featured')->orderByDesc('created_at');

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 20));

        return $this->success('OK', [
            'items' => [
                'data' => GuestLibraryItemResource::collection($paginator->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'categories' => LibraryCategory::active()
                ->ordered()
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->get(['id', 'name', 'slug']),
            'regions' => LibraryItem::regionOptions(),
        ]);
    }

    public function libraryShow(string $slug): JsonResponse
    {
        $item = LibraryItem::query()
            ->active()
            ->where('slug', $slug)
            ->with(['category:id,name,slug'])
            ->first();

        abort_unless($item, 404);

        return $this->success('Sign in required for full access', [
            'item' => (new GuestLibraryItemResource($item))->resolve(),
            'requires_auth' => true,
        ]);
    }

    public function providers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'service_type' => ['nullable', 'string', 'max:120'],
            'service_types' => ['nullable', 'array'],
            'service_types.*' => ['string', 'max:120'],
            'language' => ['nullable', 'string', 'max:32'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:32'],
            'location' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'remote_only' => ['nullable', 'boolean'],
            'free_consultation' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', Rule::in(['rating', 'reviews', 'newest', 'experience'])],
        ]);

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,city,state,country,postal_code'])
            ->active()
            ->acceptingClients();

        MarketplaceProviderFilters::apply($request, $query);

        $perPage = max(1, min(50, (int) ($validated['per_page'] ?? 20)));
        $providers = $query->paginate($perPage)->withQueryString();

        return $this->success('OK', [
            'providers' => [
                'data' => GuestProviderResource::collection($providers->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $providers->currentPage(),
                    'last_page' => $providers->lastPage(),
                    'per_page' => $providers->perPage(),
                    'total' => $providers->total(),
                ],
            ],
        ]);
    }

    public function providerShow(ServiceProvider $provider): JsonResponse
    {
        abort_unless($provider->is_active && $provider->accepting_clients, 404);

        $provider->load(['user:id,first_name,last_name,city,state,country,postal_code']);

        return $this->success('Sign in required for full access', [
            'provider' => (new GuestProviderResource($provider))->resolve(),
            'requires_auth' => true,
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
