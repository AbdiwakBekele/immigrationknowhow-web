<?php

namespace App\Support;

use App\Enums\VerificationStatus;
use App\Models\ServiceProvider;

class PublicServiceProviderPresentation
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(ServiceProvider $provider): array
    {
        $provider->append([
            'primary_service_type',
            'service_types_labels',
            'location_display',
            'display_name',
        ]);

        $excerpt = trim((string) ($provider->tagline ?: $provider->bio ?: ''));
        if ($excerpt !== '') {
            $excerpt = mb_strimwidth(strip_tags($excerpt), 0, 220, '...');
        }

        $user = $provider->user;

        return [
            'slug' => $provider->slug,
            'business_name' => $provider->business_name,
            'display_name' => $provider->display_name,
            'tagline' => $provider->tagline,
            'bio_excerpt' => $excerpt !== '' ? $excerpt : null,
            'primary_service_type' => $provider->primary_service_type,
            'service_types_labels' => $provider->service_types_labels,
            'average_rating' => (float) $provider->average_rating,
            'total_reviews' => (int) $provider->total_reviews,
            'is_featured' => (bool) $provider->is_featured,
            'is_verified' => $provider->verification_status === VerificationStatus::APPROVED,
            'background_check_clear' => $provider->background_check_status === 'clear',
            'free_consultation' => (bool) $provider->free_consultation,
            'hourly_rate' => $provider->hourly_rate !== null ? (float) $provider->hourly_rate : null,
            'consultation_fee' => $provider->consultation_fee !== null ? (float) $provider->consultation_fee : null,
            'serves_remote' => (bool) $provider->serves_remote,
            'serves_in_person' => (bool) $provider->serves_in_person,
            'service_radius_miles' => $provider->service_radius_miles,
            'location_display' => $provider->location_display,
            'profile_url' => url('/providers/'.$provider->slug),
            'user' => $user ? [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'initials' => $user->initials,
                'avatar_url' => $user->avatar_url,
                'city' => $user->city,
                'state' => $user->state,
            ] : null,
        ];
    }
}
