<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookShareEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const CONFIRM_INTENT = 'intent';

    public const CONFIRM_REFERRER = 'referrer_click';

    protected $fillable = [
        'ebook_share_campaign_id',
        'user_id',
        'library_item_id',
        'share_token',
        'platform',
        'status',
        'confirm_source',
        'intent_at',
        'confirmed_at',
        'referrer',
        'click_ip',
    ];

    protected function casts(): array
    {
        return [
            'intent_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EbookShareCampaign::class, 'ebook_share_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libraryItem(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function shareUrl(): string
    {
        return url('/s/'.$this->share_token);
    }

    public function coverImageUrl(): string
    {
        return route('ebook-share.cover', ['token' => $this->share_token]);
    }

    /**
     * @return array{share_url: string, cover_image_url: string, title: ?string, slug: ?string}
     */
    public function sharePreview(): array
    {
        $item = $this->relationLoaded('libraryItem') ? $this->libraryItem : $this->libraryItem()->first();

        return [
            'share_url' => $this->shareUrl(),
            'cover_image_url' => $this->coverImageUrl(),
            'title' => $item?->title,
            'slug' => $item?->slug,
        ];
    }
}
