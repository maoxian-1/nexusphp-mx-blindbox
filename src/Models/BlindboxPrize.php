<?php

namespace NexusPlugin\Blindbox\Models;

use Illuminate\Database\Eloquent\Model;

class BlindboxPrize extends Model
{
    protected $table = 'plugin_blindbox_prizes';

    protected $fillable = [
        'name',
        'description',
        'type',
        'value',
        'medal_id',
        'rainbow_days',
        'probability',
        'daily_limit',
        'total_limit',
        'given_count',
        'given_today',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'probability' => 'decimal:2',
        'is_active' => 'boolean',
        'daily_limit' => 'integer',
        'total_limit' => 'integer',
        'given_count' => 'integer',
        'given_today' => 'integer',
        'sort_order' => 'integer',
        'rainbow_days' => 'integer',
    ];
}
