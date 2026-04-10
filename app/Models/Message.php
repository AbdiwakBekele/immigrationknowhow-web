<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $appends = [
        'is_mine',
    ];

    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'body',
        'attachments',
        'read_at',
        'is_system_message',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'read_at' => 'datetime',
            'is_system_message' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    // Accessors
    public function getIsReadAttribute(): bool
    {
        $currentUser = auth()->user();
        if (!$currentUser || $this->sender_id === $currentUser->id) {
            return true;
        }

        return $this->reads()->where('user_id', $currentUser->id)->exists();
    }

    public function getIsMineAttribute(): bool
    {
        return $this->sender_id === auth()->id();
    }

    public function getFormattedTimeAttribute(): string
    {
        if ($this->created_at->isToday()) {
            return $this->created_at->format('g:i A');
        }

        if ($this->created_at->isYesterday()) {
            return 'Yesterday ' . $this->created_at->format('g:i A');
        }

        if ($this->created_at->isCurrentYear()) {
            return $this->created_at->format('M j, g:i A');
        }

        return $this->created_at->format('M j, Y g:i A');
    }

    // Scopes
    public function scopeUnread($query)
    {
        $currentUser = auth()->user();
        if (!$currentUser) return $query;

        return $query->where('sender_id', '!=', $currentUser->id)
            ->whereDoesntHave('reads', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
    }

    // Methods
    public function markAsRead(User $user): void
    {
        if ($this->sender_id === $user->id) {
            return;
        }

        $this->reads()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'read_at' => now(),
        ]);
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }
}
