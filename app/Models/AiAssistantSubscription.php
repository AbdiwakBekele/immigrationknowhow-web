<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiAssistantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_checkout_session_id',
        'status',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array((string) $this->status, ['active', 'trialing', 'past_due'], true);
    }

    public static function forUser(int $userId): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Persist one canonical subscription row per user (removes stale duplicates).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function upsertForUser(int $userId, array $attributes): self
    {
        return DB::transaction(function () use ($userId, $attributes) {
            $rows = static::query()
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($rows->count() > 1) {
                $duplicateIds = $rows->skip(1)->pluck('id')->all();
                static::query()->whereIn('id', $duplicateIds)->delete();
                Log::warning('ai_assistant_subscription.duplicates_removed', [
                    'user_id' => $userId,
                    'kept_id' => $rows->first()?->id,
                    'removed_ids' => $duplicateIds,
                ]);
            }

            $primary = $rows->first();
            if ($primary) {
                $primary->fill($attributes);
                $primary->save();

                return $primary->fresh() ?? $primary;
            }

            return static::query()->create(array_merge(['user_id' => $userId], $attributes));
        });
    }
}
