<?php

namespace App\Models;

use App\Support\AppleIapConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ad extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'cta_url',
        'image_url',
        'status',
        'price_cents',
        'currency',
        'paid_at',
        'published_at',
        'last_paid_checkout_started_at',
        'last_paid_checkout_session_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'published_at' => 'datetime',
            'last_paid_checkout_started_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ad): void {
            if (empty($ad->uuid)) {
                $ad->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AdPayment::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AdAnalyticsEvent::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function applePublishProductId(): ?string
    {
        if ((int) $this->price_cents <= 0) {
            return null;
        }

        if ((int) $this->price_cents !== AppleIapConfig::adPublishPriceCents()) {
            return null;
        }

        $productId = AppleIapConfig::adPublishProductId();

        return $productId !== '' ? $productId : null;
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at');
    }

    public function scopePubliclyVisible($query)
    {
        return $query->published();
    }
}

