<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'processing_status',
        'attempts',
        'processed_at',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
