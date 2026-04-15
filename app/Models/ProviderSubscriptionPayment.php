<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderSubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_subscription_id',
        'service_provider_id',
        'subscription_plan_id',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'amount_due_cents',
        'amount_paid_cents',
        'currency',
        'status',
        'billing_reason',
        'paid_at',
        'invoice_pdf_url',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function providerSubscription(): BelongsTo
    {
        return $this->belongsTo(ProviderSubscription::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
