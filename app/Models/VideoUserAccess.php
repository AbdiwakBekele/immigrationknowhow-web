<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoUserAccess extends Model
{
    protected $table = 'video_user_access';

    protected $fillable = [
        'user_id',
        'video_embed_id',
        'purchased_at',
        'purchase_amount',
        'purchase_currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'purchase_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(VideoEmbed::class, 'video_embed_id');
    }
}

