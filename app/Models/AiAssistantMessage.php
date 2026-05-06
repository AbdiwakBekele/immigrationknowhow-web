<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssistantMessage extends Model
{
    protected $fillable = [
        'user_id',
        'context',
        'role',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}

