<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReferralVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'affiliate_code',
        'session_id',
        'fingerprint_hash',
        'ip_address',
        'user_agent',
        'referrer_url',
        'landing_url',
        'landing_path',
        'query_params',
        'is_unique',
        'visited_at',
        'attribution_expires_at',
        'registered_user_id',
    ];

    protected function casts(): array
    {
        return [
            'query_params' => 'array',
            'is_unique' => 'boolean',
            'visited_at' => 'datetime',
            'attribution_expires_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function registeredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }
}
