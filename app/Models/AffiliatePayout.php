<?php

namespace App\Models;

use App\Enums\AffiliatePayoutStatus;
use App\Models\Affiliate;
use App\Models\AffiliatePayoutItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'amount',
        'currency',
        'payout_date',
        'payment_method',
        'payment_reference',
        'payment_details',
        'notes',
        'status',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payout_date' => 'date',
            'payment_details' => 'array',
            'status' => AffiliatePayoutStatus::class,
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AffiliatePayoutItem::class);
    }
}
