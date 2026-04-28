<?php

namespace App\Models;

use App\Enums\ContractState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'lead_id',
        'conversation_id',
        'service_provider_id',
        'user_id',
        'pricing_model',
        'currency',
        'offered_rate',
        'agreed_rate',
        'state',
        'offered_at',
        'accepted_at',
        'withdrawn_at',
        'ended_at',
        'ended_reason',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'offered_rate' => 'decimal:2',
            'agreed_rate' => 'decimal:2',
            'state' => ContractState::class,
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $contract) {
            if (empty($contract->uuid)) {
                $contract->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class)->latest('id');
    }
}
