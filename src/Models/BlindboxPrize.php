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
        'value_min',
        'value_max',
        'medal_id',
        'medal_bonus',
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
        'value_min' => 'integer',
        'value_max' => 'integer',
        'probability' => 'decimal:2',
        'medal_bonus' => 'decimal:2',
        'is_active' => 'boolean',
        'daily_limit' => 'integer',
        'total_limit' => 'integer',
        'given_count' => 'integer',
        'given_today' => 'integer',
        'sort_order' => 'integer',
        'rainbow_days' => 'integer',
    ];

    /**
     * 获取实际奖励值（支持随机范围）
     * 仅对 bonus 和 upload 类型生效
     */
    public function getActualValue(): int
    {
        // 仅 bonus 和 upload 支持随机范围
        if (!in_array($this->type, ['bonus', 'upload'])) {
            return (int) $this->value;
        }

        // 如果设置了最小值和最大值，则生成随机数
        if ($this->value_min !== null && $this->value_max !== null && $this->value_min < $this->value_max) {
            return mt_rand((int) $this->value_min, (int) $this->value_max);
        }

        return (int) $this->value;
    }

    /**
     * 获取奖励值的显示文本
     */
    public function getValueDisplayAttribute(): string
    {
        if ($this->value_min !== null && $this->value_max !== null && in_array($this->type, ['bonus', 'upload'])) {
            if ($this->type === 'upload') {
                $minGB = number_format($this->value_min / 1073741824, 2);
                $maxGB = number_format($this->value_max / 1073741824, 2);
                return "{$minGB} - {$maxGB} GB";
            }
            return "{$this->value_min} - {$this->value_max}";
        }

        if ($this->type === 'upload') {
            return number_format($this->value / 1073741824, 2) . ' GB';
        }

        return (string) $this->value;
    }
}
