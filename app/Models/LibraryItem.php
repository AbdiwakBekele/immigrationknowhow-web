<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class LibraryItem extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    /** Disk for e-book / audiobook binaries (not publicly linked). */
    public const LIBRARY_MEDIA_DISK = 'library_media';

    public const TYPE_DEFINITIONS = [
        'ebook' => [
            'label' => 'E-Book',
            'icon' => 'book-open',
            'allowed_extensions' => ['pdf'],
        ],
        'audiobook' => [
            'label' => 'Audiobook',
            'icon' => 'musical-note',
            'allowed_extensions' => ['mp3', 'm4a', 'aac', 'wav', 'ogg'],
        ],
    ];

    /**
     * Stored on disk but must never be exposed to the browser — otherwise files
     * can be downloaded directly from /storage/... without going through access checks.
     */
    protected $hidden = [
        'file_path',
    ];

    protected $fillable = [
        'uuid',
        'provider_id',
        'category_id',
        'title',
        'slug',
        'type',
        'description',
        'author',
        'publisher',
        'publication_year',
        'isbn',
        'tags',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'cover_image',
        'duration_seconds',
        'narrator',
        'is_premium',
        'price',
        'currency',
        'is_featured',
        'is_active',
        'download_count',
        'view_count',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_premium' => 'boolean',
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
            'download_count' => 'integer',
            'view_count' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
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

    /**
     * Where the main library file currently lives (private disk first, then legacy public).
     */
    public function resolveLibraryFileDisk(): ?string
    {
        if (! $this->file_path) {
            return null;
        }
        if (Storage::disk(self::LIBRARY_MEDIA_DISK)->exists($this->file_path)) {
            return self::LIBRARY_MEDIA_DISK;
        }
        if (Storage::disk('public')->exists($this->file_path)) {
            return 'public';
        }

        return null;
    }

    public function deleteStoredLibraryFile(): void
    {
        if (! $this->file_path) {
            return;
        }
        foreach ([self::LIBRARY_MEDIA_DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($this->file_path)) {
                Storage::disk($disk)->delete($this->file_path);
            }
        }
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function userAccess(): HasMany
    {
        return $this->hasMany(LibraryUserAccess::class);
    }

    // Accessors
    public function getTypeIconAttribute(): string
    {
        return static::TYPE_DEFINITIONS[$this->type]['icon'] ?? 'document';
    }

    public function getTypeLabelAttribute(): string
    {
        return static::TYPE_DEFINITIONS[$this->type]['label'] ?? ucfirst($this->type);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : null;
    }

    /**
     * Library binaries are not served via /storage URLs.
     */
    public function getFileUrlAttribute(): ?string
    {
        return null;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration_seconds) return null;

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes} min";
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

    public function scopeEbooks($query)
    {
        return $query->where('type', 'ebook');
    }

    public function scopeAudiobooks($query)
    {
        return $query->where('type', 'audiobook');
    }

    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('author', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Methods
    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    public function recordAccess(User $user): LibraryUserAccess
    {
        $access = LibraryUserAccess::firstOrCreate([
            'user_id' => $user->id,
            'library_item_id' => $this->id,
        ]);

        $access->increment('access_count');
        $access->update(['last_accessed_at' => now()]);

        return $access;
    }

    public static function supportedTypes(): array
    {
        return array_keys(static::TYPE_DEFINITIONS);
    }

    public static function typeLabel(string $type): string
    {
        return static::TYPE_DEFINITIONS[$type]['label'] ?? ucfirst($type);
    }

    public static function allowedExtensionsFor(string $type): array
    {
        return static::TYPE_DEFINITIONS[$type]['allowed_extensions'] ?? [];
    }

    public static function typeOptionsWithCounts(bool $activeOnly = true): array
    {
        $query = static::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $counts = $query
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return collect(static::TYPE_DEFINITIONS)
            ->map(function (array $definition, string $value) use ($counts) {
                return [
                    'value' => $value,
                    'label' => $definition['label'],
                    'count' => (int) ($counts[$value] ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}
