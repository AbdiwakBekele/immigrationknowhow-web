<?php

namespace App\Models;

use App\Enums\AffiliateEarningStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommissionRule;
use App\Models\AffiliatePayoutItem;
use App\Models\AffiliateReferral;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'referred_user_id',
        'affiliate_referral_id',
        'source_type',
        'source_id',
        'event_type',
        'commission_rule_id',
        'rule_scope_snapshot',
        'commission_type_snapshot',
        'commission_value_snapshot',
        'base_amount',
        'commission_amount',
        'currency',
        'status',
        'approved_by',
        'rejected_by',
        'approved_at',
        'rejected_at',
        'paid_at',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliateEarningStatus::class,
            'commission_value_snapshot' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
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

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'affiliate_referral_id');
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommissionRule::class, 'commission_rule_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(AffiliatePayoutItem::class);
    }

    public function approve(?User $user = null): void
    {
        $this->update([
            'status' => AffiliateEarningStatus::APPROVED,
            'approved_by' => $user?->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
        ]);
    }

    public function reject(?User $user = null, ?string $notes = null): void
    {
        $this->update([
            'status' => AffiliateEarningStatus::REJECTED,
            'rejected_by' => $user?->id,
            'rejected_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function markPaid(): void
    {
        $this->update([
            'status' => AffiliateEarningStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
