<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookCoupon extends Model
{
    public const ISSUED_FOR_SIGNUP = 'signup';

    public const ISSUED_FOR_SOCIAL_SHARE = 'social_share';

    protected $fillable = [
        'user_id',
        'code',
        'issued_for',
        'redeemed_at',
        'library_item_id',
        'library_user_access_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libraryItem(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class);
    }

    public function libraryUserAccess(): BelongsTo
    {
        return $this->belongsTo(LibraryUserAccess::class);
    }

    public function isRedeemed(): bool
    {
        return $this->redeemed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRedeemed() && ! $this->isExpired();
    }

    public static function activeSignupCouponForUser(int $userId): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('issued_for', self::ISSUED_FOR_SIGNUP)
            ->whereNull('redeemed_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    public static function activeSocialShareCouponForUser(int $userId): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('issued_for', self::ISSUED_FOR_SOCIAL_SHARE)
            ->whereNull('redeemed_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    public static function activeCouponForUser(int $userId): ?self
    {
        return self::activeSignupCouponForUser($userId)
            ?? self::activeSocialShareCouponForUser($userId);
    }
}
