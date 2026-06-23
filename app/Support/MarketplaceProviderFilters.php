<?php

namespace App\Support;

use App\Models\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MarketplaceProviderFilters
{
    /**
     * @param  Builder<ServiceProvider>  $query
     */
    public static function apply(Request $request, Builder $query): void
    {
        $serviceTypes = collect($request->input('service_types', []))
            ->when($request->filled('service_type'), fn ($c) => $c->push((string) $request->input('service_type')))
            ->filter(fn ($type) => is_string($type) && trim($type) !== '')
            ->map(fn ($type) => trim((string) $type))
            ->unique()
            ->values()
            ->all();

        if ($serviceTypes !== []) {
            $query->where(function ($q) use ($serviceTypes) {
                foreach ($serviceTypes as $type) {
                    $q->orWhereJsonContains('service_types', $type);
                }
            });
        }

        $languages = collect($request->input('languages', []))
            ->when($request->filled('language'), fn ($c) => $c->push((string) $request->input('language')))
            ->filter(fn ($language) => is_string($language) && trim($language) !== '')
            ->map(fn ($language) => trim((string) $language))
            ->unique()
            ->values()
            ->all();

        if ($languages !== []) {
            $query->where(function ($q) use ($languages) {
                foreach ($languages as $language) {
                    $q->orWhereJsonContains('languages_offered', $language);
                }
            });
        }

        if ($request->filled('location')) {
            $location = (string) $request->input('location');
            $query->where(function ($q) use ($location) {
                $q->where('serves_remote', true)
                    ->orWhereHas('user', function ($uq) use ($location) {
                        $uq->where('city', 'like', "%{$location}%")
                            ->orWhere('state', 'like', "%{$location}%")
                            ->orWhere('postal_code', 'like', "%{$location}%");
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
    }
}
