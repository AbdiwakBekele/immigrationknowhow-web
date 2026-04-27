<?php

namespace App\Models;

use App\Models\AffiliateReferral;
use App\Enums\LeadStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Contract / offer lifecycle is tracked on the lead (status and contract_* timestamps),
 * not by auto-posting chat messages. When the user sends a contract, the lead reflects
 * an offer sent and pending the provider. When the provider accepts, the lead moves to
 * In Progress and contract acceptance is recorded. Offered/agreed contract rates
 * are stored so later billing can use the accepted amount.
 */
class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'service_provider_id',
        'service_type',
        'status',
        'message',
        'requirements',
        'preferred_contact_method',
        'preferred_contact_time',
        'urgency',
        'needed_by',
        'budget_range',
        'viewed_at',
        'responded_at',
        'contract_sent_at',
        'contract_accepted_at',
        'contract_offered_rate',
        'contract_agreed_rate',
        'contract_id',
        'converted_at',
        'closed_at',
        'provider_notes',
        'decline_reason',
        'source',
        'referral_code',
        'affiliate_referral_id',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'status' => LeadStatus::class,
            'needed_by' => 'date',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
            'contract_sent_at' => 'datetime',
            'contract_accepted_at' => 'datetime',
            'contract_offered_rate' => 'decimal:2',
            'contract_agreed_rate' => 'decimal:2',
            'converted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lead) {
            if (empty($lead->uuid)) {
                $lead->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function affiliateReferral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class);
    }

    // Accessors
    public function getServiceTypeLabelAttribute(): string
    {
        return ServiceType::tryFrom($this->service_type)?->label() ?? $this->service_type;
    }

    public function getUrgencyColorAttribute(): string
    {
        return match ($this->urgency) {
            'urgent' => 'red',
            'high' => 'orange',
            'normal' => 'blue',
            'low' => 'gray',
            default => 'gray',
        };
    }

    public function getIsNewAttribute(): bool
    {
        return $this->status === LeadStatus::NEW;
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, [
            LeadStatus::NEW,
            LeadStatus::CONTACTED,
            LeadStatus::IN_PROGRESS,
        ]);
    }

    // Scopes
    public function scopeNew($query)
    {
        return $query->where('status', LeadStatus::NEW);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            LeadStatus::NEW,
            LeadStatus::CONTACTED,
            LeadStatus::IN_PROGRESS,
        ]);
    }

    public function scopeClosed($query)
    {
        return $query->whereIn('status', [
            LeadStatus::CONVERTED,
            LeadStatus::CLOSED,
            LeadStatus::DECLINED,
        ]);
    }

    public function scopeByServiceType($query, string $type)
    {
        return $query->where('service_type', $type);
    }

    public function scopeUrgent($query)
    {
        return $query->whereIn('urgency', ['urgent', 'high']);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Status Transitions
    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->update(['viewed_at' => now()]);
        }
    }

    public function markAsContacted(): void
    {
        $this->update([
            'status' => LeadStatus::CONTACTED,
            'responded_at' => now(),
        ]);
    }

    public function markAsInProgress(): void
    {
        $this->update(['status' => LeadStatus::IN_PROGRESS]);
    }

    public function markAsConverted(): void
    {
        $this->update([
            'status' => LeadStatus::CONVERTED,
            'converted_at' => now(),
            'closed_at' => now(),
        ]);
    }

    public function markAsClosed(): void
    {
        $this->update([
            'status' => LeadStatus::CLOSED,
            'closed_at' => now(),
        ]);
    }

    public function decline(?string $reason = null): void
    {
        $this->update([
            'status' => LeadStatus::DECLINED,
            'decline_reason' => $reason,
            'closed_at' => now(),
        ]);
    }

    // Helper Methods
    public function canBeResponded(): bool
    {
        return $this->isOpen && $this->status !== LeadStatus::IN_PROGRESS;
    }

    /**
     * Creates the single message thread for this inquiry (controlled, inquiry-based messaging).
     */
    public function createConversation(): Conversation
    {
        return Conversation::create([
            'lead_id' => $this->id,
            'user_id' => $this->user_id,
            'service_provider_id' => $this->service_provider_id,
            'subject' => "Inquiry: {$this->service_type_label}",
        ]);
    }
}
