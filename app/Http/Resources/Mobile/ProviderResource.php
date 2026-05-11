<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'business_name' => $p->business_name,
            'tagline' => $p->tagline,
            'bio' => $p->bio,
            'description' => $p->description,
            'service_types' => $p->service_types ?? [],
            'languages_offered' => $p->languages_offered ?? [],
            'pricing_model' => $p->pricing_model,
            'hourly_rate' => $p->hourly_rate,
            'consultation_fee' => $p->consultation_fee,
            'free_consultation' => (bool) $p->free_consultation,
            'serves_remote' => (bool) $p->serves_remote,
            'serves_in_person' => (bool) $p->serves_in_person,
            'years_experience' => $p->years_experience,
            'average_rating' => $p->average_rating,
            'total_reviews' => $p->total_reviews,
            'is_featured' => (bool) $p->is_featured,
            'is_favorited' => (bool) ($p->is_favorited ?? false),
            'accepting_clients' => (bool) $p->accepting_clients,
            'location_display' => $p->location_display,
            'user' => $p->relationLoaded('user') ? [
                'first_name' => $p->user?->first_name,
                'last_name' => $p->user?->last_name,
                'avatar_url' => $p->user?->avatar_url,
                'city' => $p->user?->city,
                'state' => $p->user?->state,
                'country' => $p->user?->country,
            ] : null,
            'reviews' => $p->relationLoaded('reviews')
                ? ReviewResource::collection($p->reviews)->resolve()
                : [],
            'profile_feed' => $p->relationLoaded('profilePosts')
                ? collect($p->profilePosts)->map(fn ($post) => method_exists($post, 'toFeedPayload') ? $post->toFeedPayload() : null)
                    ->filter()
                    ->values()
                    ->all()
                : [],
        ];
    }
}
