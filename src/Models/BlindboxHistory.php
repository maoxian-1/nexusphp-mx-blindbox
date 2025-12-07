<?php

namespace NexusPlugin\Blindbox\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BlindboxHistory extends Model
{
    protected $table = 'plugin_blindbox_history';

    protected $fillable = [
        'user_id',
        'prize_id',
        'prize_name',
        'prize_type',
        'prize_value',
        'is_free',
        'cost',
        'ip',
    ];

    protected $casts = [
        'prize_value' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_free' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prize()
    {
        return $this->belongsTo(BlindboxPrize::class, 'prize_id');
    }
}
