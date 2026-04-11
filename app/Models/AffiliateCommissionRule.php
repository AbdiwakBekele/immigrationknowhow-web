<?php

namespace App\Models;

use App\Enums\AffiliateCommissionScope;
use App\Enums\AffiliateCommissionTrigger;
use App\Enums\AffiliateCommissionType;
use App\Models\Affiliate;
use App\Models\AffiliateEarning;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateCommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope',
        'trigger_event',
        'affiliate_id',
        'referred_user_id',
        'commission_type',
        'commission_value',
        'currency',
        'priority',
        'starts_at',
        'ends_at',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope' => AffiliateCommissionScope::class,
            'trigger_event' => AffiliateCommissionTrigger::class,
            'commission_type' => AffiliateCommissionType::class,
            'commission_value' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(AffiliateEarning::class, 'commission_rule_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($ruleQuery) {
                $ruleQuery->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($ruleQuery) {
                $ruleQuery->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
