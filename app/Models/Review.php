<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'service_provider_id',
        'lead_id',
        'rating',
        'comment',
        'communication_rating',
        'expertise_rating',
        'value_rating',
        'responsiveness_rating',
        'provider_response',
        'provider_responded_at',
        'is_verified',
        'is_approved',
        'is_featured',
        'moderation_notes',
        'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'communication_rating' => 'integer',
            'expertise_rating' => 'integer',
            'value_rating' => 'integer',
            'responsiveness_rating' => 'integer',
            'provider_responded_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_approved' => 'boolean',
            'is_featured' => 'boolean',
            'helpful_count' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($review) {
            if (empty($review->uuid)) {
                $review->uuid = (string) Str::uuid();
            }
        });

        static::saved(function ($review) {
            // Update provider's average rating
            $review->serviceProvider->updateRating();
        });

        static::deleted(function ($review) {
            $review->serviceProvider->updateRating();
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    // Accessors
    public function getRatingStarsAttribute(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    public function getAverageDetailedRatingAttribute(): ?float
    {
        $ratings = array_filter([
            $this->communication_rating,
            $this->expertise_rating,
            $this->value_rating,
            $this->responsiveness_rating,
        ]);

        if (empty($ratings)) return null;
        
        return round(array_sum($ratings) / count($ratings), 1);
    }

    public function getHasProviderResponseAttribute(): bool
    {
        return !empty($this->provider_response);
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeWithResponse($query)
    {
        return $query->whereNotNull('provider_response');
    }

    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeHighRated($query, int $minRating = 4)
    {
        return $query->where('rating', '>=', $minRating);
    }

    public function scopeRecent($query, int $days = 90)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Methods
    public function addProviderResponse(string $response): void
    {
        $this->update([
            'provider_response' => $response,
            'provider_responded_at' => now(),
        ]);
    }

    public function markAsVerified(): void
    {
        $this->update(['is_verified' => true]);
    }

    public function approve(): void
    {
        $this->update(['is_approved' => true]);
    }

    public function reject(string $reason = null): void
    {
        $this->update([
            'is_approved' => false,
            'moderation_notes' => $reason,
        ]);
    }

    public function markAsHelpful(User $user): void
    {
        $existingVote = $this->votes()->where('user_id', $user->id)->first();
        
        if ($existingVote) {
            if (!$existingVote->is_helpful) {
                $existingVote->update(['is_helpful' => true]);
                $this->increment('helpful_count');
            }
        } else {
            $this->votes()->create([
                'user_id' => $user->id,
                'is_helpful' => true,
            ]);
            $this->increment('helpful_count');
        }
    }

    public function markAsNotHelpful(User $user): void
    {
        $existingVote = $this->votes()->where('user_id', $user->id)->first();
        
        if ($existingVote) {
            if ($existingVote->is_helpful) {
                $existingVote->update(['is_helpful' => false]);
                $this->decrement('helpful_count');
            }
        } else {
            $this->votes()->create([
                'user_id' => $user->id,
                'is_helpful' => false,
            ]);
        }
    }
}
