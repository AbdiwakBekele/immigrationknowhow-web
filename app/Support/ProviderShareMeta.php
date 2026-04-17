<?php

namespace App\Support;

use App\Models\ServiceProvider;
use Illuminate\Support\Str;

class ProviderShareMeta
{
    /**
     * @return array{url: string, title: string, description: string, image: string}
     */
    public static function forProvider(ServiceProvider $provider): array
    {
        $provider->loadMissing('user:id,first_name,last_name,avatar');

        $title = $provider->business_name ?: 'Provider';

        $description = $provider->tagline
            ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($provider->bio ?? '')))), 200);

        if ($description === '') {
            $description = sprintf('View %s on %s.', $title, config('app.name'));
        }

        $image = $provider->user?->avatar_url;
        if (! $image) {
            $image = 'https://ui-avatars.com/api/?name='.rawurlencode($title).'&background=3B95F3&color=fff&size=512';
        }

        return [
            'url' => route('marketplace.show', $provider->slug),
            'title' => $title,
            'description' => $description,
            'image' => $image,
        ];
    }
}
