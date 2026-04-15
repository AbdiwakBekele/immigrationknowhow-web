<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class SubscriptionPlan extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_cents',
        'currency',
        'billing_cycle',
        'features',
        'status',
        'is_featured',
        'sort_order',
        'stripe_product_id',
        'stripe_price_id',
        'commission_type',
        'commission_value',
        'recurring_commission_enabled',
        'max_recurring_commission_cycles',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_featured' => 'boolean',
            'recurring_commission_enabled' => 'boolean',
            'commission_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (empty($plan->uuid)) {
                $plan->uuid = (string) Str::uuid();
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
        return 'uuid';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ProviderSubscription::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
