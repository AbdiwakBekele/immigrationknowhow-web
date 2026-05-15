<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPostReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_post_id',
        'user_id',
        'old_wp_reaction_id',
        'old_wp_post_id',
        'old_wp_user_id',
        'dedupe_key',
        'type',
        'import_source',
        'imported_at',
    ];

    protected $casts = [
        'old_wp_reaction_id' => 'integer',
        'old_wp_post_id' => 'integer',
        'old_wp_user_id' => 'integer',
        'imported_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
