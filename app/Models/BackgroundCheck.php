<?php

namespace App\Models;

use App\Enums\BackgroundCheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BackgroundCheck extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'service_provider_id',
        'checkr_candidate_id',
        'checkr_invitation_id',
        'checkr_report_id',
        'status',
        'adjudication',
        'package',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'zipcode',
        'dob',
        'ssn_last_four',
        'completed_at',
        'expires_at',
        'report_summary',
        'metadata',
        'last_webhook_at',
        'webhook_history',
    ];

    protected $appends = ['status_display'];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'report_summary' => 'array',
            'metadata' => 'array',
            'webhook_history' => 'array',
            'status' => BackgroundCheckStatus::class,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($check) {
            if (empty($check->uuid)) {
                $check->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getMaskedSsnAttribute(): ?string
    {
        return $this->ssn_last_four ? "XXX-XX-{$this->ssn_last_four}" : null;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsValidAttribute(): bool
    {
        return $this->status === BackgroundCheckStatus::CLEAR && !$this->is_expired;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return (int) now()->diffInDays($this->expires_at, false);
    }

    public function getStatusDisplayAttribute(): array
    {
        return [
            'status' => $this->status->value,
            'label' => $this->status->label(),
            'description' => $this->status->description(),
            'color' => $this->status->color(),
            'badge_classes' => $this->status->badgeClasses(),
            'icon' => $this->status->icon(),
        ];
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', BackgroundCheckStatus::PENDING);
    }

    public function scopeInvited($query)
    {
        return $query->where('status', BackgroundCheckStatus::INVITED);
    }

    public function scopeCleared($query)
    {
        return $query->where('status', BackgroundCheckStatus::CLEAR);
    }

    public function scopeValid($query)
    {
        return $query->where('status', BackgroundCheckStatus::CLEAR)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('status', [
            BackgroundCheckStatus::CONSIDER,
            BackgroundCheckStatus::SUSPENDED,
            BackgroundCheckStatus::DISPUTE,
        ]);
    }

    // Methods
    public function markAsInvited(string $invitationId): void
    {
        $this->update([
            'checkr_invitation_id' => $invitationId,
            'status' => BackgroundCheckStatus::INVITED,
        ]);
    }

    public function markAsCompleted(string $reportId): void
    {
        $this->update([
            'checkr_report_id' => $reportId,
            'status' => BackgroundCheckStatus::COMPLETED,
        ]);
    }

    public function markAsCleared(array $reportData = []): void
    {
        $this->update([
            'status' => BackgroundCheckStatus::CLEAR,
            'completed_at' => now(),
            'expires_at' => now()->addYear(), // Background checks valid for 1 year
            'report_summary' => $reportData,
        ]);

        // Update provider status
        $this->serviceProvider->update([
            'background_check_status' => BackgroundCheckStatus::CLEAR->value,
            'background_check_verified_at' => now(),
        ]);
    }

    public function markAsConsider(array $reportData = []): void
    {
        $this->update([
            'status' => BackgroundCheckStatus::CONSIDER,
            'completed_at' => now(),
            'report_summary' => $reportData,
        ]);

        $this->serviceProvider->update([
            'background_check_status' => BackgroundCheckStatus::CONSIDER->value,
        ]);
    }

    public function recordWebhook(string $eventType, array $payload): void
    {
        $history = $this->webhook_history ?? [];
        $history[] = [
            'event' => $eventType,
            'received_at' => now()->toISOString(),
            'payload_summary' => [
                'type' => $payload['type'] ?? null,
                'object' => $payload['object'] ?? null,
            ],
        ];

        $this->update([
            'last_webhook_at' => now(),
            'webhook_history' => $history,
        ]);
    }

    public function canInitiate(): bool
    {
        return in_array($this->status, [
            BackgroundCheckStatus::PENDING,
            BackgroundCheckStatus::EXPIRED,
        ]);
    }
}
