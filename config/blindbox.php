<?php

return [
    // 是否启用盲盒功能
    'enabled' => true,

    // 每次抽奖消耗的魔力值
    'draw_cost' => 100,

    // 是否启用每日免费抽奖
    'daily_free' => true,

    // 每日免费抽奖次数
    'daily_free_times' => 1,

    // 默认奖品配置
    'default_prizes' => [
        [
            'name' => '少量魔力值',
            'type' => 'bonus',
            'value' => 50,
            'probability' => 30,
            'description' => '获得50魔力值'
        ],
        [
            'name' => '中量魔力值',
            'type' => 'bonus',
            'value' => 100,
            'probability' => 20,
            'description' => '获得100魔力值'
        ],
        [
            'name' => '大量魔力值',
            'type' => 'bonus',
            'value' => 500,
            'probability' => 5,
            'description' => '获得500魔力值'
        ],
        [
            'name' => '1GB上传量',
            'type' => 'upload',
            'value' => 1073741824, // 1GB in bytes
            'probability' => 15,
            'description' => '获得1GB上传量'
        ],
        [
            'name' => '5GB上传量',
            'type' => 'upload',
            'value' => 5368709120, // 5GB in bytes
            'probability' => 10,
            'description' => '获得5GB上传量'
        ],
        [
            'name' => 'VIP 1天',
            'type' => 'vip_days',
            'value' => 1,
            'probability' => 10,
            'description' => '获得1天VIP会员'
        ],
        [
            'name' => 'VIP 7天',
            'type' => 'vip_days',
            'value' => 7,
            'probability' => 5,
            'description' => '获得7天VIP会员'
        ],
        [
            'name' => '临时邀请名额',
            'type' => 'invite',
            'value' => 1,
            'probability' => 3,
            'description' => '获得1个临时邀请名额'
        ],
        [
            'name' => '彩虹ID 7天',
            'type' => 'rainbow_id',
            'value' => 7,
            'probability' => 2,
            'description' => '获得7天彩虹ID特权'
        ]
    ]
];
