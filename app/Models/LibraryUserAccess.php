<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryUserAccess extends Model
{
    protected $table = 'library_user_access';

    protected $fillable = [
        'user_id',
        'library_item_id',
        'last_accessed_at',
        'access_count',
        'progress',
        'is_favorite',
        'purchased_at',
        'purchase_amount',
        'purchase_currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'manual_payment_requested_at',
        'manual_payment_reference',
        'manual_payment_note',
    ];

    protected function casts(): array
    {
        return [
            'last_accessed_at' => 'datetime',
            'progress' => 'array',
            'is_favorite' => 'boolean',
            'access_count' => 'integer',
            'purchased_at' => 'datetime',
            'manual_payment_requested_at' => 'datetime',
            'purchase_amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libraryItem(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class);
    }

    // Methods
    public function updateProgress(array $progress, ?string $mode = null): void
    {
        $cleanProgress = collect($progress)
            ->filter(fn ($value) => $value !== null)
            ->all();

        if ($mode === null) {
            $this->update(['progress' => $cleanProgress]);

            return;
        }

        $current = is_array($this->progress) ? $this->progress : [];
        $modeProgress = is_array($current[$mode] ?? null) ? $current[$mode] : [];
        $current[$mode] = array_merge($modeProgress, $cleanProgress, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->update(['progress' => $current]);
    }

    public function toggleFavorite(): void
    {
        $this->update(['is_favorite' => ! $this->is_favorite]);
    }
}
