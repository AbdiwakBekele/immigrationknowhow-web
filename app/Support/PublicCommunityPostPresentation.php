<?php

namespace App\Support;

use App\Models\CommunityPost;

class PublicCommunityPostPresentation
{
    /**
     * @param  array<int, string>  $userReactions
     * @return array<string, mixed>
     */
    public static function payload(CommunityPost $post, array $userReactions = []): array
    {
        $importMeta = is_array($post->import_meta) ? $post->import_meta : [];
        $contributor = $post->relationLoaded('contributor') ? $post->contributor : null;

        $contributorName = $contributor?->full_name;
        if (! filled($contributorName)) {
            $fallbackEmail = trim((string) ($importMeta['contributor_email'] ?? ''));
            $contributorName = $fallbackEmail !== '' ? $fallbackEmail : null;
        }

        $contributorCountrySource = filled($post->contributor_country)
            ? (string) $post->contributor_country
            : ($contributor?->country ?? null);

        $contributorCountry = CountryDisplay::labelForDisplay($contributorCountrySource);

        $imageUrl = trim((string) ($post->image_url ?? ''));
        if ($imageUrl === '' && filled($post->description)) {
            $imageUrl = self::extractImageFromDescription((string) $post->description);
        }

        return [
            'id' => $post->id,
            'title' => $post->title,
            'description' => $post->description,
            'tag' => $post->tag,
            'category' => $post->category,
            'contributor_name' => $contributorName,
            'contributor_country' => $contributorCountry,
            'image_url' => self::absoluteMediaUrl($imageUrl !== '' ? $imageUrl : null),
            'video_url' => self::absoluteMediaUrl($post->video_url),
            'likes_count' => (int) $post->likes_count,
            'comments_count' => (int) $post->comments_count,
            'shares_count' => (int) $post->shares_count,
            'bookmarks_count' => (int) $post->bookmarks_count,
            'user_reactions' => $userReactions,
            'created_at' => optional($post->created_at)->toIso8601String(),
        ];
    }

    public static function absoluteMediaUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return url('/'.ltrim($url, '/'));
    }

    public static function extractImageFromDescription(string $description): string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches)) {
            return $matches[1] ?? '';
        }

        return '';
    }
}
