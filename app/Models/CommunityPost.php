<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'contributor_user_id',
        'contributor_country',
        'old_wp_post_id',
        'old_wp_author_id',
        'old_wp_space_id',
        'title',
        'slug',
        'description',
        'tag',
        'category',
        'image_url',
        'video_url',
        'likes_count',
        'comments_count',
        'shares_count',
        'bookmarks_count',
        'is_published',
        'published_at',
        'import_source',
        'import_meta',
        'imported_at',
    ];

    protected $casts = [
        'old_wp_post_id' => 'integer',
        'old_wp_author_id' => 'integer',
        'old_wp_space_id' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'import_meta' => 'array',
        'imported_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommunityPostReaction::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
