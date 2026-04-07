<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClick extends Model
{
    protected $fillable = [
        'affiliate_link_id',
        'user_id',
        'ip_address',
        'user_agent',
        'referrer',
        'page_source',
    ];

    // Relationships
    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
