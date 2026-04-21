<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdAnalyticsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_id',
        'event_type',
        'ip_address',
        'user_agent',
        'referrer',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}

