<?php

namespace App\Support;

use App\Models\CommunityPost;
use App\Models\LibraryItem;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

final class PublicSocialPreview
{
    /**
     * @param  array{url: string, title: string, description: string, image?: ?string}  $meta
     */
    public static function apply(array $meta): void
    {
        View::share('socialPreview', self::normalize($meta));
    }

    /**
     * @return array{url: string, title: string, description: string, image: string, site_name: string}
     */
    public static function forCommunityPost(CommunityPost $post): array
    {
        $imageUrl = trim((string) ($post->image_url ?? ''));
        if ($imageUrl === '' && filled($post->description)) {
            $imageUrl = PublicCommunityPostPresentation::extractImageFromDescription((string) $post->description);
        }

        $description = Str::limit(
            trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post->description ?? '')))),
            200
        );

        if ($description === '') {
            $description = sprintf('Read this post on %s Community.', config('app.name'));
        }

        return self::normalize([
            'url' => route('community.post-page', $post),
            'title' => (string) $post->title,
            'description' => $description,
            'image' => PublicCommunityPostPresentation::absoluteMediaUrl($imageUrl !== '' ? $imageUrl : null),
        ]);
    }

    /**
     * @return array{url: string, title: string, description: string, image: string, site_name: string}
     */
    public static function forLibraryItem(LibraryItem $item): array
    {
        $description = Str::limit(
            trim(preg_replace('/\s+/', ' ', strip_tags((string) ($item->description ?? '')))),
            200
        );

        if ($description === '') {
            $description = sprintf('Discover "%s" in the %s library.', $item->title, config('app.name'));
        }

        return self::normalize([
            'url' => route('library.show', $item),
            'title' => (string) $item->title,
            'description' => $description,
            'image' => $item->cover_image_url,
        ]);
    }

    /**
     * @param  array{url: string, title: string, description: string, image?: ?string}  $meta
     * @return array{url: string, title: string, description: string, image: string, site_name: string}
     */
    public static function normalize(array $meta): array
    {
        $image = trim((string) ($meta['image'] ?? ''));
        if ($image !== '') {
            $image = self::absoluteUrl($image);
        } else {
            $image = self::defaultImage();
        }

        return [
            'url' => self::absoluteUrl((string) $meta['url']),
            'title' => (string) $meta['title'],
            'description' => (string) $meta['description'],
            'image' => $image,
            'site_name' => (string) config('app.name'),
        ];
    }

    public static function defaultImage(): string
    {
        $branding = PlatformSetting::branding();
        $logo = trim((string) ($branding['site_logo_url'] ?? ''));
        if ($logo !== '' && ! str_ends_with(strtolower($logo), '.svg')) {
            return self::absoluteUrl($logo);
        }

        $company = (string) ($branding['company_name'] ?? config('app.name'));

        return 'https://ui-avatars.com/api/?name='.rawurlencode($company).'&background=3B95F3&color=fff&size=1200&format=png';
    }

    public static function absoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return url('/');
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return url('/'.ltrim($url, '/'));
    }
}
