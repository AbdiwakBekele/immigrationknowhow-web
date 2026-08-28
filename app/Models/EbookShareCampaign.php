<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbookShareCampaign extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REWARDED = 'rewarded';

    protected $fillable = [
        'user_id',
        'status',
        'required_shares',
        'completed_at',
        'rewarded_at',
        'ebook_coupon_id',
    ];

    protected function casts(): array
    {
        return [
            'required_shares' => 'integer',
            'completed_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(EbookCoupon::class, 'ebook_coupon_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EbookShareEvent::class);
    }

    public function confirmedEvents(): HasMany
    {
        return $this->events()->where('status', EbookShareEvent::STATUS_CONFIRMED);
    }

    public function confirmedShareCount(): int
    {
        return (int) $this->confirmedEvents()->count();
    }

    public function isComplete(): bool
    {
        return $this->confirmedShareCount() >= (int) $this->required_shares;
    }

    public static function activeForUser(int $userId): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('status', self::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();
    }

    public static function latestForUser(int $userId): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->first();
    }
}
