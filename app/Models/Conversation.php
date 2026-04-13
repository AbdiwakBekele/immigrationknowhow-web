<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'lead_id',
        'user_id',
        'service_provider_id',
        'subject',
        'last_message_at',
        'user_archived',
        'provider_archived',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'user_archived' => 'boolean',
            'provider_archived' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($conversation) {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Only threads created from a service inquiry (lead). No standalone / public community messaging.
     */
    public function scopeForServiceInquiries($query)
    {
        return $query->whereNotNull('lead_id');
    }

    // Accessors
    public function getOtherParticipantAttribute()
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return null;
        }

        if ($currentUser->id === $this->user_id) {
            return $this->serviceProvider?->user;
        }

        return $this->user;
    }

    public function getUnreadCountAttribute(): int
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $currentUser->id)
            ->whereDoesntHave('reads', function ($query) use ($currentUser) {
                $query->where('user_id', $currentUser->id);
            })
            ->count();
    }

    public function getIsArchivedAttribute(): bool
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return false;
        }

        if ($currentUser->id === $this->user_id) {
            return $this->user_archived;
        }

        if ($currentUser->serviceProvider?->id === $this->service_provider_id) {
            return $this->provider_archived;
        }

        return false;
    }

    // Scopes
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where(function ($owningUserQuery) use ($user) {
                $owningUserQuery->where('user_id', $user->id)
                    ->where('user_archived', false);
            });

            if ($user->serviceProvider) {
                $q->orWhere(function ($providerQuery) use ($user) {
                    $providerQuery->where('service_provider_id', $user->serviceProvider->id)
                        ->where('provider_archived', false);
                });
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->whereNotNull('last_message_at');
    }

    public function scopeWithUnread($query)
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return $query;
        }

        return $query->whereHas('messages', function ($q) use ($currentUser) {
            $q->where('sender_id', '!=', $currentUser->id)
                ->whereDoesntHave('reads', function ($rq) use ($currentUser) {
                    $rq->where('user_id', $currentUser->id);
                });
        });
    }

    // Methods
    public function addMessage(User $sender, string $body, ?array $attachments = null): Message
    {
        $message = $this->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
            'attachments' => $attachments,
        ]);

        $this->update(['last_message_at' => now()]);

        return $message;
    }

    public function archive(User $user): void
    {
        if ($user->id === $this->user_id) {
            $this->update(['user_archived' => true]);
        } elseif ($user->serviceProvider?->id === $this->service_provider_id) {
            $this->update(['provider_archived' => true]);
        }
    }

    public function unarchive(User $user): void
    {
        if ($user->id === $this->user_id) {
            $this->update(['user_archived' => false]);
        } elseif ($user->serviceProvider?->id === $this->service_provider_id) {
            $this->update(['provider_archived' => false]);
        }
    }

    public function markAllAsRead(User $user): void
    {
        $unreadMessages = $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        foreach ($unreadMessages as $message) {
            $message->markAsRead($user);
        }
    }
}
