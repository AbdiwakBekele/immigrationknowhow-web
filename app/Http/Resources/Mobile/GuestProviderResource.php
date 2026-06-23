<?php

namespace App\Http\Resources\Mobile;

use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ServiceProvider $provider */
        $provider = $this->resource;
        $businessType = $provider->primary_service_type ?: 'Service provider';

        $locationParts = array_values(array_filter([
            $provider->user?->state,
            $provider->user?->city,
        ], fn ($part) => is_string($part) && trim($part) !== ''));

        return [
            'slug' => $provider->slug,
            'avatar_url' => $provider->user?->avatar_url,
            'business_type' => $businessType,
            'location' => $locationParts !== [] ? implode(', ', $locationParts) : 'Location not specified',
        ];
    }
}
