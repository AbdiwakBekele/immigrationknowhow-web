<?php

namespace App\Models;

use App\Models\Affiliate;
use App\Models\AffiliateEarning;
use App\Models\AffiliateReferralVisit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'referred_user_id',
        'first_visit_id',
        'last_visit_id',
        'attributed_visit_id',
        'attribution_model',
        'registered_at',
        'last_conversion_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'last_conversion_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function firstVisit(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferralVisit::class, 'first_visit_id');
    }

    public function lastVisit(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferralVisit::class, 'last_visit_id');
    }

    public function attributedVisit(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferralVisit::class, 'attributed_visit_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(AffiliateEarning::class);
    }
}
