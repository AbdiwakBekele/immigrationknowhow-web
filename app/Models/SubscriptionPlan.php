<?php

namespace App\Models;

use App\Support\AppleIapConfig;
use Illuminate\Database\Eloquent\Builder;
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
        'service_type_option_id',
        'stripe_product_id',
        'stripe_price_id',
        'apple_product_id',
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

    public function serviceTypeOption(): BelongsTo
    {
        return $this->belongsTo(ServiceTypeOption::class, 'service_type_option_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function appleProductId(): string
    {
        $configured = trim((string) ($this->apple_product_id ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return AppleIapConfig::providerProductIdForBillingCycle($this->billing_cycle, (string) $this->uuid);
    }

    /**
     * @param  list<int|string>  $values  Service type option `value` strings (from the provider profile / onboarding)
     */
    public function scopeForProviderServiceTypeValues(Builder $query, array $values): void
    {
        $normalized = array_values(array_filter(
            array_map(fn ($v) => is_string($v) || is_numeric($v) ? trim((string) $v) : '', $values),
            fn (string $v) => $v !== ''
        ));

        if ($normalized === []) {
            $query->whereNull('service_type_option_id');

            return;
        }

        $ids = ServiceTypeOption::query()
            ->whereIn('value', $normalized)
            ->pluck('id');

        $query->where(function (Builder $q) use ($ids) {
            $q->whereNull('service_type_option_id');
            if ($ids->isNotEmpty()) {
                $q->orWhereIn('service_type_option_id', $ids);
            }
        });
    }
}
