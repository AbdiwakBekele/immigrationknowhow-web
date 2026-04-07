<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class AffiliateLink extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'url',
        'tracking_code',
        'category',
        'service_types',
        'logo',
        'button_text',
        'placement',
        'click_count',
        'conversion_count',
        'commission_type',
        'commission_value',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_types' => 'array',
            'placement' => 'array',
            'commission_value' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'click_count' => 'integer',
            'conversion_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($link) {
            if (empty($link->uuid)) {
                $link->uuid = (string) Str::uuid();
            }
            if (empty($link->tracking_code)) {
                $link->tracking_code = strtoupper(Str::random(8));
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    // Accessors
    public function getTrackedUrlAttribute(): string
    {
        return route('affiliate.track', $this->tracking_code);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->click_count === 0) return 0;
        return round(($this->conversion_count / $this->click_count) * 100, 2);
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

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForPlacement($query, string $placement)
    {
        return $query->whereJsonContains('placement', $placement);
    }

    public function scopeForServiceType($query, string $serviceType)
    {
        return $query->whereJsonContains('service_types', $serviceType);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Methods
    public function recordClick(?User $user = null, array $metadata = []): AffiliateClick
    {
        $this->increment('click_count');

        return $this->clicks()->create([
            'user_id' => $user?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => request()->header('referer'),
            'page_source' => $metadata['page_source'] ?? null,
        ]);
    }

    public function recordConversion(): void
    {
        $this->increment('conversion_count');
    }
}
