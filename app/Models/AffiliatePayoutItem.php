<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePayoutItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_payout_id',
        'affiliate_earning_id',
        'amount_paid',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function earning(): BelongsTo
    {
        return $this->belongsTo(AffiliateEarning::class, 'affiliate_earning_id');
    }
}
