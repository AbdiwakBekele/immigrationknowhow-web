<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class IdentityVerification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'document_type',
        'document_number',
        'document_country',
        'document_expiry',
        'front_image',
        'back_image',
        'selfie_image',
        'status',
        'rejection_reason',
        'verification_notes',
        'reviewed_by',
        'reviewed_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'document_expiry' => 'date',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'verification_notes' => 'array',
            'metadata' => 'array',
            'status' => VerificationStatus::class,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($verification) {
            if (empty($verification->uuid)) {
                $verification->uuid = (string) Str::uuid();
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Accessors
    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'passport' => 'Passport',
            'drivers_license' => "Driver's License",
            'national_id' => 'National ID Card',
            'professional_license' => 'Professional License',
            default => ucfirst(str_replace('_', ' ', $this->document_type)),
        };
    }

    public function getFrontImageUrlAttribute(): ?string
    {
        return $this->front_image ? asset('storage/' . $this->front_image) : null;
    }

    public function getBackImageUrlAttribute(): ?string
    {
        return $this->back_image ? asset('storage/' . $this->back_image) : null;
    }

    public function getSelfieImageUrlAttribute(): ?string
    {
        return $this->selfie_image ? asset('storage/' . $this->selfie_image) : null;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getDocumentIsExpiredAttribute(): bool
    {
        return $this->document_expiry && $this->document_expiry->isPast();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', VerificationStatus::PENDING);
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', VerificationStatus::UNDER_REVIEW);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', VerificationStatus::APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', VerificationStatus::REJECTED);
    }

    public function scopeNeedsReview($query)
    {
        return $query->whereIn('status', [
            VerificationStatus::PENDING,
            VerificationStatus::UNDER_REVIEW,
        ]);
    }

    // Methods
    public function approve(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => VerificationStatus::APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'verification_notes' => array_merge($this->verification_notes ?? [], [
                ['action' => 'approved', 'by' => $reviewer->id, 'at' => now()->toISOString(), 'notes' => $notes],
            ]),
            'expires_at' => now()->addYear(), // Verification valid for 1 year
        ]);

        // Update provider verification status if applicable
        if ($provider = $this->user->serviceProvider) {
            $provider->update([
                'verification_status' => VerificationStatus::APPROVED,
                'verified_at' => now(),
            ]);
        }
    }

    public function reject(User $reviewer, string $reason, ?string $notes = null): void
    {
        $this->update([
            'status' => VerificationStatus::REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'verification_notes' => array_merge($this->verification_notes ?? [], [
                ['action' => 'rejected', 'by' => $reviewer->id, 'at' => now()->toISOString(), 'reason' => $reason, 'notes' => $notes],
            ]),
        ]);

        // Update provider verification status if applicable
        if ($provider = $this->user->serviceProvider) {
            $provider->update([
                'verification_status' => VerificationStatus::REJECTED,
            ]);
        }
    }

    public function startReview(User $reviewer): void
    {
        $this->update([
            'status' => VerificationStatus::UNDER_REVIEW,
            'reviewed_by' => $reviewer->id,
            'verification_notes' => array_merge($this->verification_notes ?? [], [
                ['action' => 'review_started', 'by' => $reviewer->id, 'at' => now()->toISOString()],
            ]),
        ]);
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status, [
            VerificationStatus::PENDING,
            VerificationStatus::UNDER_REVIEW,
        ]);
    }
}
