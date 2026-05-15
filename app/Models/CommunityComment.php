<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_post_id',
        'user_id',
        'parent_id',
        'old_wp_comment_id',
        'old_wp_post_id',
        'old_wp_user_id',
        'old_wp_parent_comment_id',
        'author_name',
        'content',
        'import_source',
        'imported_at',
    ];

    protected $casts = [
        'old_wp_comment_id' => 'integer',
        'old_wp_post_id' => 'integer',
        'old_wp_user_id' => 'integer',
        'old_wp_parent_comment_id' => 'integer',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
