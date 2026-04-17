<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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

    public const REGION_DEFINITIONS = [
        'usa' => 'USA',
        'canada' => 'Canada',
        'great_britain' => 'Great Britain',
        'europe' => 'Europe',
    ];

    /**
     * Stored on disk but must never be exposed to the browser — otherwise files
     * can be downloaded directly from /storage/... without going through access checks.
     */
    protected $hidden = [
        'file_path',
        'audio_file_path',
    ];

    protected $appends = [
        'cover_image_url',
        'has_audio_companion',
    ];

    protected $fillable = [
        'uuid',
        'provider_id',
        'category_id',
        'title',
        'slug',
        'type',
        'regions',
        'description',
        'author_id',
        'publisher',
        'publication_year',
        'isbn',
        'tags',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'audio_file_path',
        'audio_file_name',
        'audio_file_size',
        'audio_file_type',
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
            'regions' => 'array',
            'is_premium' => 'boolean',
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'file_size' => 'integer',
            'audio_file_size' => 'integer',
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

    /**
     * Optional audiobook file bundled with an e-book (same purchase / access).
     */
    public function resolveAudioFileDisk(): ?string
    {
        if (! $this->audio_file_path) {
            return null;
        }
        if (Storage::disk(self::LIBRARY_MEDIA_DISK)->exists($this->audio_file_path)) {
            return self::LIBRARY_MEDIA_DISK;
        }
        if (Storage::disk('public')->exists($this->audio_file_path)) {
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

    public function deleteStoredAudioFile(): void
    {
        if (! $this->audio_file_path) {
            return;
        }
        foreach ([self::LIBRARY_MEDIA_DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($this->audio_file_path)) {
                Storage::disk($disk)->delete($this->audio_file_path);
            }
        }
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function libraryAuthor(): BelongsTo
    {
        return $this->belongsTo(LibraryAuthor::class, 'author_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    protected function author(): Attribute
    {
        return Attribute::get(fn () => $this->libraryAuthor?->name);
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
        return $this->cover_image ? asset('storage/'.$this->cover_image) : null;
    }

    public function getHasAudioCompanionAttribute(): bool
    {
        return $this->type === 'ebook' && filled($this->audio_file_path);
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
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

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
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('libraryAuthor', function ($authorQuery) use ($search) {
                    $authorQuery->where('name', 'like', "%{$search}%");
                });
        });
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Items available worldwide (no regions set) or whose JSON `regions` array includes $region.
     * MySQL needs JSON_CONTAINS with a JSON-encoded scalar; whereJsonContains is unreliable for JSON arrays on some drivers.
     *
     * @param  Builder  $query
     */
    public function scopeWhereRegionsMatchOrGlobal($query, string $region): void
    {
        $query->where(function ($q) use ($region) {
            $q->whereNull('regions')
                ->orWhereJsonLength('regions', 0)
                ->orWhere(function ($q2) use ($region) {
                    static::applyRegionsArrayContains($q2, $region);
                });
        });
    }

    public function scopeAvailableInRegion($query, ?string $region)
    {
        if (! is_string($region) || $region === '') {
            return $query;
        }

        $query->whereRegionsMatchOrGlobal($region);

        return $query;
    }

    /**
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    protected static function applyRegionsArrayContains($query, string $region): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->whereRaw('JSON_CONTAINS(regions, ?)', [json_encode($region)]);

            return;
        }

        if ($driver === 'pgsql') {
            $query->whereRaw('regions::jsonb @> ?::jsonb', [json_encode([$region])]);

            return;
        }

        $query->whereJsonContains('regions', $region);
    }

    public static function supportedRegions(): array
    {
        return array_keys(static::REGION_DEFINITIONS);
    }

    public static function regionOptions(): array
    {
        return collect(static::REGION_DEFINITIONS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    public static function regionForCountry(?string $country): ?string
    {
        $country = is_string($country) ? trim($country) : '';
        if ($country === '') {
            return null;
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $country));

        if (in_array($normalized, ['united states', 'united states of america', 'usa', 'us', 'u.s.', 'u.s.a.'], true)) {
            return 'usa';
        }

        if ($normalized === 'canada') {
            return 'canada';
        }

        if (in_array($normalized, ['united kingdom', 'uk', 'u.k.', 'great britain', 'britain', 'england', 'scotland', 'wales', 'northern ireland'], true)) {
            return 'great_britain';
        }

        // EU + a few common European countries (best-effort mapping from free-text country field).
        $europe = [
            'austria', 'belgium', 'bulgaria', 'croatia', 'cyprus', 'czech republic', 'czechia', 'denmark',
            'estonia', 'finland', 'france', 'germany', 'greece', 'hungary', 'ireland', 'italy', 'latvia',
            'lithuania', 'luxembourg', 'malta', 'netherlands', 'poland', 'portugal', 'romania', 'slovakia',
            'slovenia', 'spain', 'sweden', 'norway', 'switzerland', 'iceland',
        ];

        if (in_array($normalized, $europe, true)) {
            return 'europe';
        }

        return null;
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
