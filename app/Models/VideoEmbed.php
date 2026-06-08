<?php

namespace App\Models;

use App\Support\AppleIapConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class VideoEmbed extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    public const SOURCE_EMBED = 'embed';

    public const SOURCE_UPLOAD = 'upload';

    /** Stored on disk — never expose as a public URL. */
    public const VIDEO_UPLOAD_DISK = 'video_uploads';

    protected $fillable = [
        'uuid',
        'created_by',
        'source',
        'title',
        'slug',
        'description',
        'platform',
        'video_url',
        'video_id',
        'embed_code',
        'thumbnail_url',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'category',
        'tags',
        'view_count',
        'is_featured',
        'is_active',
        'sort_order',
        'price',
        'currency',
        'apple_product_id',
    ];

    protected $hidden = [
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'view_count' => 'integer',
            'sort_order' => 'integer',
            'file_size' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($video) {
            if (empty($video->uuid)) {
                $video->uuid = (string) Str::uuid();
            }
            if (($video->source ?? self::SOURCE_EMBED) === self::SOURCE_UPLOAD) {
                return;
            }
            $video->extractVideoInfo();
        });

        static::updating(function ($video) {
            if (($video->source ?? self::SOURCE_EMBED) === self::SOURCE_UPLOAD) {
                return;
            }
            if ($video->isDirty('video_url')) {
                $video->extractVideoInfo();
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function userAccess(): HasMany
    {
        return $this->hasMany(VideoUserAccess::class, 'video_embed_id');
    }

    // Accessors
    public function getPlatformIconAttribute(): string
    {
        return match ($this->platform) {
            'youtube' => 'play',
            'tiktok' => 'musical-note',
            'vimeo' => 'video-camera',
            'upload' => 'video-camera',
            default => 'play',
        };
    }

    public function getPlatformLabelAttribute(): string
    {
        return match ($this->platform) {
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'vimeo' => 'Vimeo',
            'upload' => 'Uploaded file',
            default => ucfirst((string) $this->platform),
        };
    }

    public function isUpload(): bool
    {
        return $this->source === self::SOURCE_UPLOAD;
    }

    public function appleProductId(): string
    {
        $configured = trim((string) ($this->apple_product_id ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return AppleIapConfig::defaultVideoProductId((string) $this->uuid);
    }

    public function getEmbedHtmlAttribute(): string
    {
        if ($this->embed_code) {
            return $this->embed_code;
        }

        return match ($this->platform) {
            'youtube' => sprintf(
                '<iframe width="560" height="315" src="https://www.youtube.com/embed/%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
                $this->video_id
            ),
            'vimeo' => sprintf(
                '<iframe src="https://player.vimeo.com/video/%s" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>',
                $this->video_id
            ),
            'tiktok' => sprintf(
                '<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@user/video/%s" data-video-id="%s"><section></section></blockquote><script async src="https://www.tiktok.com/embed.js"></script>',
                $this->video_id,
                $this->video_id
            ),
            'upload' => '',
            default => '',
        };
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at', 'desc');
    }

    // Methods
    public function extractVideoInfo(): void
    {
        $url = $this->video_url;
        if ($url === null || $url === '') {
            return;
        }

        // YouTube
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
            $this->platform = 'youtube';
            $this->video_id = $matches[1];
            $this->thumbnail_url = "https://img.youtube.com/vi/{$matches[1]}/maxresdefault.jpg";

            return;
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
            $this->platform = 'vimeo';
            $this->video_id = $matches[1];

            return;
        }

        // TikTok
        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $matches)) {
            $this->platform = 'tiktok';
            $this->video_id = $matches[1];

            return;
        }

        // Generic
        $this->platform = 'other';
        $this->video_id = md5($url);
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    public function canEmbed(): bool
    {
        return in_array($this->platform, ['youtube', 'vimeo', 'tiktok']);
    }
}
