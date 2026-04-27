<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAssistantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_checkout_session_id',
        'status',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array((string) $this->status, ['active', 'trialing', 'past_due'], true);
    }
}
