<?php

namespace App\Models;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AffiliateInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'invited_by',
        'name',
        'email',
        'phone',
        'notes',
        'commission_type_override',
        'commission_value_override',
        'token_hash',
        'expires_at',
        'sent_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_value_override' => 'decimal:2',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->accepted_at && ! $this->cancelled_at && ! $this->hasExpired();
    }
}
