<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProviderSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_provider_id',
        'subscription_plan_id',
        'status',
        'started_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'ended_at',
        'renewal_count',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_checkout_session_id',
        'apple_product_id',
        'apple_original_transaction_id',
        'apple_transaction_id',
        'affiliate_id',
        'affiliate_referral_id',
        'affiliate_attribution_type',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Nested route keys like {plan} are pluralized to plans() by default; this subscription uses belongsTo plan().
     */
    protected function childRouteBindingRelationshipName($childType): string
    {
        if ($childType === 'plan') {
            return 'plan';
        }

        return parent::childRouteBindingRelationshipName($childType);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProviderSubscriptionPayment::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function affiliateReferral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class);
    }
}
