<?php

namespace App\Models;

use App\Enums\AffiliateCommissionType;
use App\Enums\AffiliateStatus;
use App\Models\AffiliateCommissionRule;
use App\Models\AffiliateEarning;
use App\Models\AffiliateInvite;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralVisit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Affiliate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'code',
        'status',
        'phone',
        'company_name',
        'website_url',
        'social_profile_url',
        'notes',
        'commission_type_override',
        'commission_value_override',
        'payout_method',
        'payout_details',
        'invited_by',
        'activated_at',
        'deactivated_at',
        'last_attribution_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'commission_type_override' => AffiliateCommissionType::class,
            'commission_value_override' => 'decimal:2',
            'payout_details' => 'array',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_attribution_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Affiliate $affiliate) {
            if (blank($affiliate->code)) {
                do {
                    $code = Str::upper(Str::random(8));
                } while (static::where('code', $code)->exists());

                $affiliate->code = $code;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invite(): HasOne
    {
        return $this->hasOne(AffiliateInvite::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(AffiliateReferralVisit::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(AffiliateCommissionRule::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(AffiliateEarning::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', AffiliateStatus::ACTIVE->value);
    }

    public function isPortalAccessible(): bool
    {
        return in_array($this->status, [AffiliateStatus::VERIFIED, AffiliateStatus::ACTIVE], true)
            && $this->user?->hasVerifiedEmail()
            && $this->user?->is_active;
    }

    public function getReferralUrlAttribute(): string
    {
        return route('home', ['ref' => $this->code]);
    }
}
