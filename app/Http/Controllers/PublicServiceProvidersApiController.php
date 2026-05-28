<?php

namespace App\Http\Controllers;

use App\Models\ServiceProvider;
use App\Support\PublicServiceProviderPresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicServiceProvidersApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['rating', 'reviews', 'newest', 'experience'])],
            'remote_only' => ['nullable', 'boolean'],
            'free_consultation' => ['nullable', 'boolean'],
        ]);

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state'])
            ->active()
            ->acceptingClients();

        if (! empty($validated['service_type'])) {
            $query->byServiceType($validated['service_type']);
        }

        if (! empty($validated['language'])) {
            $query->byLanguage($validated['language']);
        }

        if (! empty($validated['location'])) {
            $location = $validated['location'];
            $query->where(function ($q) use ($location) {
                $q->where('serves_remote', true)
                    ->orWhereHas('user', function ($uq) use ($location) {
                        $uq->where('city', 'like', "%{$location}%")
                            ->orWhere('state', 'like', "%{$location}%");
                    });
            });
        }

        if (! empty($validated['search'])) {
            $query->search($validated['search']);
        }

        if ($request->boolean('remote_only')) {
            $query->where('serves_remote', true);
        }

        if ($request->boolean('free_consultation')) {
            $query->where('free_consultation', true);
        }

        $sortBy = $validated['sort'] ?? 'rating';
        $query->when($sortBy === 'rating', fn ($q) => $q->orderByDesc('average_rating')->orderByDesc('total_reviews'))
            ->when($sortBy === 'reviews', fn ($q) => $q->orderByDesc('total_reviews'))
            ->when($sortBy === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->when($sortBy === 'experience', fn ($q) => $q->orderByDesc('years_experience'));

        $query->orderByDesc('is_featured');

        $providers = $query
            ->paginate((int) ($validated['per_page'] ?? 12))
            ->through(fn (ServiceProvider $provider): array => PublicServiceProviderPresentation::payload($provider));

        return response()->json($providers);
    }
}
