<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class ServiceProvider extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'slug',
        'bio',
        'description',
        'tagline',
        'business_email',
        'business_phone',
        'website',
        'service_types',
        'specializations',
        'languages_offered',
        'pricing_model',
        'hourly_rate',
        'consultation_fee',
        'free_consultation',
        'pricing_notes',
        'serves_remote',
        'serves_in_person',
        'service_radius_miles',
        'service_areas',
        'license_number',
        'license_state',
        'license_expiry',
        'state_license_document_path',
        'state_license_document_name',
        'certifications',
        'health_certificates',
        'years_experience',
        'linkedin_url',
        'facebook_url',
        'twitter_url',
        'instagram_url',
        'youtube_url',
        'tiktok_url',
        'total_leads',
        'total_reviews',
        'average_rating',
        'profile_views',
        'verification_status',
        'is_verified',
        'verified_at',
        'is_featured',
        'is_active',
        'accepting_clients',
        'subscription_plan',
        'subscription_expires_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_subscription_status',
        'stripe_current_period_end',
        'background_check_status',
        'background_check_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'service_types' => 'array',
            'specializations' => 'array',
            'languages_offered' => 'array',
            'service_areas' => 'array',
            'certifications' => 'array',
            'health_certificates' => 'array',
            'hourly_rate' => 'decimal:2',
            'consultation_fee' => 'decimal:2',
            'average_rating' => 'decimal:2',
            'free_consultation' => 'boolean',
            'serves_remote' => 'boolean',
            'serves_in_person' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'accepting_clients' => 'boolean',
            'license_expiry' => 'date',
            'verified_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'stripe_current_period_end' => 'datetime',
            'verification_status' => VerificationStatus::class,
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('business_name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'provider_favorites', 'service_provider_id', 'user_id')
            ->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function libraryItems(): HasMany
    {
        return $this->hasMany(LibraryItem::class, 'provider_id');
    }

    public function profilePosts(): HasMany
    {
        return $this->hasMany(ProviderProfilePost::class);
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return $this->business_name ?: $this->user->full_name;
    }

    public function getServiceTypesLabelsAttribute(): array
    {
        return collect($this->service_types ?? [])
            ->map(fn ($type) => $this->resolveServiceTypeLabel(is_string($type) ? $type : (string) $type))
            ->toArray();
    }

    public function getPrimaryServiceTypeAttribute(): ?string
    {
        $first = $this->service_types[0] ?? null;
        if ($first === null || $first === '') {
            return null;
        }

        return $this->resolveServiceTypeLabel(is_string($first) ? $first : (string) $first);
    }

    protected function resolveServiceTypeLabel(string $value): string
    {
        if ($enum = ServiceType::tryFrom($value)) {
            return $enum->label();
        }

        $optionLabels = once(function () {
            if (! Schema::hasTable('service_type_options')) {
                return [];
            }
            try {
                return ServiceTypeOption::query()->pluck('label', 'value')->all();
            } catch (\Throwable) {
                return [];
            }
        });

        if (isset($optionLabels[$value])) {
            return $optionLabels[$value];
        }

        return Str::of($value)->replace(['_', '-'], ' ')->title()->toString();
    }

    public function getLocationDisplayAttribute(): string
    {
        $parts = array_filter([
            $this->user->city,
            $this->user->state,
        ]);

        return implode(', ', $parts) ?: 'Location not specified';
    }

    public function getRatingDisplayAttribute(): string
    {
        if ($this->total_reviews === 0) {
            return 'No reviews yet';
        }

        return number_format($this->average_rating, 1).' ('.$this->total_reviews.' reviews)';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', VerificationStatus::APPROVED);
    }

    public function scopeAcceptingClients($query)
    {
        return $query->where('accepting_clients', true);
    }

    /**
     * Limit providers to those whose account country matches (e.g. ISO code from CountryOptions).
     * Empty/null country skips the constraint so guests or incomplete profiles still see listings.
     */
    public function scopeWhereUserCountry($query, ?string $country)
    {
        $country = is_string($country) ? trim($country) : '';
        if ($country === '') {
            return $query;
        }

        return $query->whereHas('user', fn ($q) => $q->where('country', $country));
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByServiceType($query, string|ServiceType $type)
    {
        $value = $type instanceof ServiceType ? $type->value : $type;

        return $query->whereJsonContains('service_types', $value);
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->whereJsonContains('languages_offered', $language);
    }

    public function scopeServesLocation($query, ?string $city = null, ?string $state = null)
    {
        return $query->where(function ($q) use ($city, $state) {
            $q->where('serves_remote', true)
                ->orWhereHas('user', function ($uq) use ($city, $state) {
                    if ($city) {
                        $uq->where('city', 'like', "%{$city}%");
                    }
                    if ($state) {
                        $uq->where('state', $state);
                    }
                });
        });
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('business_name', 'like', "%{$search}%")
                ->orWhere('bio', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
        });
    }

    // Helper Methods
    public function isVerified(): bool
    {
        return $this->background_check_status === 'clear'
            && $this->background_check_verified_at
            && (! $this->latestBackgroundCheck()?->is_expired ?? true);
    }

    public function hasValidBackgroundCheck(): bool
    {
        return $this->background_check_status === 'clear'
            && $this->background_check_verified_at
            && $this->latestBackgroundCheck()?->is_valid;
    }

    public function latestBackgroundCheck()
    {
        return $this->hasMany(BackgroundCheck::class)
            ->latest()
            ->first();
    }

    public function backgroundChecks()
    {
        return $this->hasMany(BackgroundCheck::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ProviderSubscription::class, 'service_provider_id');
    }

    public function currentSubscription()
    {
        return $this->hasOne(ProviderSubscription::class, 'service_provider_id')
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latestOfMany();
    }

    public function hasActiveSubscription(): bool
    {
        return in_array((string) $this->stripe_subscription_status, ['active', 'trialing', 'past_due'], true)
            || ($this->subscription_plan &&
                (! $this->subscription_expires_at || $this->subscription_expires_at->isFuture()));
    }

    public function incrementProfileViews(): void
    {
        $this->increment('profile_views');
    }

    public function updateRating(): void
    {
        $stats = $this->reviews()
            ->where('is_approved', true)
            ->selectRaw('COUNT(*) as count, AVG(rating) as average')
            ->first();

        $this->updateQuietly([
            'total_reviews' => $stats->count ?? 0,
            'average_rating' => round($stats->average ?? 0, 2),
        ]);
    }

    public function incrementLeadCount(): void
    {
        $this->increment('total_leads');
    }
}
