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
    ];

    protected function casts(): array
    {
        return [
            'last_accessed_at' => 'datetime',
            'progress' => 'array',
            'is_favorite' => 'boolean',
            'access_count' => 'integer',
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
    public function updateProgress(array $progress): void
    {
        $this->update(['progress' => $progress]);
    }

    public function toggleFavorite(): void
    {
        $this->update(['is_favorite' => !$this->is_favorite]);
    }
}
