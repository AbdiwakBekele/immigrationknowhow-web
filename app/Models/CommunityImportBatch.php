<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityImportBatch extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_READY = 'ready';
    public const STATUS_IMPORTING = 'importing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';

    public const IMPORT_TYPE_WP_COMMUNITY = 'wp_community';

    protected $fillable = [
        'uploaded_by',
        'status',
        'import_type',
        'users_file_path',
        'posts_file_path',
        'comments_file_path',
        'reactions_file_path',
        'total_users',
        'imported_users',
        'skipped_users',
        'total_posts',
        'imported_posts',
        'skipped_posts',
        'total_comments',
        'imported_comments',
        'skipped_comments',
        'total_reactions',
        'imported_reactions',
        'skipped_reactions',
        'errors_count',
        'summary',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(CommunityImportError::class);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_UPLOADED,
            self::STATUS_VALIDATING,
            self::STATUS_READY,
            self::STATUS_IMPORTING,
            self::STATUS_COMPLETED,
            self::STATUS_COMPLETED_WITH_ERRORS,
            self::STATUS_FAILED,
        ];
    }

    public function canImport(): bool
    {
        return ($this->summary['can_import'] ?? false) === true
            && in_array($this->status, [self::STATUS_READY, self::STATUS_FAILED], true);
    }
}
