<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProviderProfilePost extends Model
{
    public const TYPE_VIDEO = 'video';

    public const TYPE_ARTICLE = 'article';

    protected $fillable = [
        'uuid',
        'service_provider_id',
        'type',
        'url',
        'title',
        'caption',
        'platform',
        'video_id',
        'thumbnail_url',
    ];

    protected $hidden = [
        'id',
        'service_provider_id',
    ];

    protected function casts(): array
    {
        return [];
    }

    protected static function booted(): void
    {
        static::creating(function (ProviderProfilePost $post) {
            if (empty($post->uuid)) {
                $post->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (ProviderProfilePost $post) {
            if ($post->type === self::TYPE_VIDEO) {
                $post->applyParsedVideoMetadata();
            } else {
                $post->platform = null;
                $post->video_id = null;
                $post->thumbnail_url = null;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function applyParsedVideoMetadata(): void
    {
        $url = (string) $this->url;
        if ($url === '') {
            $this->platform = null;
            $this->video_id = null;
            $this->thumbnail_url = null;

            return;
        }

        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $m)) {
            $this->platform = 'youtube';
            $this->video_id = $m[1];
            $this->thumbnail_url = 'https://img.youtube.com/vi/'.$m[1].'/hqdefault.jpg';

            return;
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m)) {
            $this->platform = 'vimeo';
            $this->video_id = $m[1];
            $this->thumbnail_url = null;

            return;
        }

        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $m)) {
            $this->platform = 'tiktok';
            $this->video_id = $m[1];
            $this->thumbnail_url = null;

            return;
        }

        if (preg_match('#instagram\.com/(p|reel|reels)/([A-Za-z0-9_-]+)#i', $url, $m)) {
            $this->platform = 'instagram';
            $segment = strtolower($m[1]) === 'p' ? 'p' : 'reel';
            $this->video_id = $segment.'/'.$m[2];
            $this->thumbnail_url = null;

            return;
        }

        $this->platform = 'other';
        $this->video_id = null;
        $this->thumbnail_url = null;
    }

    public function canEmbedVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO
            && in_array((string) $this->platform, ['youtube', 'vimeo', 'tiktok', 'instagram'], true)
            && filled($this->video_id);
    }

    public function embedSrc(): ?string
    {
        if (! $this->canEmbedVideo()) {
            return null;
        }

        return match ($this->platform) {
            'youtube' => 'https://www.youtube.com/embed/'.$this->video_id.'?rel=0',
            'vimeo' => 'https://player.vimeo.com/video/'.$this->video_id,
            'tiktok' => 'https://www.tiktok.com/player/v1/'.$this->video_id.'?music_info=1&description=1',
            'instagram' => 'https://www.instagram.com/'.$this->video_id.'/embed/',
            default => null,
        };
    }

    public function linkHostname(): ?string
    {
        $host = parse_url((string) $this->url, PHP_URL_HOST);

        return is_string($host) && $host !== ''
            ? (string) preg_replace('/^www\./i', '', $host)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFeedPayload(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'url' => $this->url,
            'title' => $this->title,
            'caption' => $this->caption,
            'platform' => $this->platform,
            'platform_label' => $this->platformLabel(),
            'link_hostname' => $this->linkHostname(),
            'thumbnail_url' => $this->thumbnail_url,
            'embed_src' => $this->embedSrc(),
            'can_embed' => $this->canEmbedVideo(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function platformLabel(): ?string
    {
        if ($this->type === self::TYPE_ARTICLE) {
            return 'Article';
        }

        return match ((string) $this->platform) {
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'vimeo' => 'Vimeo',
            'instagram' => 'Instagram',
            'other' => 'Video link',
            default => null,
        };
    }
}
