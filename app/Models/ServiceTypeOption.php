<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceTypeOption extends Model
{
    protected $table = 'service_type_options';

    protected $fillable = [
        'value',
        'label',
        'icon',
        'for_user',
        'for_provider',
        'include_certificate',
        'is_active',
        'sort_order',
        'monthly_subscription_rate',
    ];

    protected $casts = [
        'for_user' => 'boolean',
        'for_provider' => 'boolean',
        'include_certificate' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'monthly_subscription_rate' => 'decimal:2',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
