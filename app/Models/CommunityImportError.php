<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityImportError extends Model
{
    use HasFactory;

    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';

    protected $fillable = [
        'community_import_batch_id',
        'file_type',
        'row_number',
        'old_wp_id',
        'severity',
        'message',
        'row_data',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommunityImportBatch::class, 'community_import_batch_id');
    }
}
