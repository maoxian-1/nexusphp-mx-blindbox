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

    // 是否在种子详情页显示盲盒
    'show_on_torrent' => false,

    // 默认奖品配置
    'default_prizes' => [
        [
            'name' => '随机少量魔力值',
            'type' => 'bonus',
            'value' => 50,
            'value_min' => 10,
            'value_max' => 100,
            'probability' => 30,
            'description' => '获得10-100随机魔力值'
        ],
        [
            'name' => '随机中量魔力值',
            'type' => 'bonus',
            'value' => 200,
            'value_min' => 100,
            'value_max' => 300,
            'probability' => 15,
            'description' => '获得100-300随机魔力值'
        ],
        [
            'name' => '随机大量魔力值',
            'type' => 'bonus',
            'value' => 500,
            'value_min' => 300,
            'value_max' => 800,
            'probability' => 5,
            'description' => '获得300-800随机魔力值'
        ],
        [
            'name' => '随机上传量',
            'type' => 'upload',
            'value' => 1073741824, // 1GB in bytes
            'value_min' => 536870912,  // 0.5GB
            'value_max' => 2147483648, // 2GB
            'probability' => 20,
            'description' => '获得0.5-2GB随机上传量'
        ],
        [
            'name' => '大量上传量',
            'type' => 'upload',
            'value' => 5368709120, // 5GB in bytes
            'value_min' => 3221225472,  // 3GB
            'value_max' => 10737418240, // 10GB
            'probability' => 8,
            'description' => '获得3-10GB随机上传量'
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
            'probability' => 4,
            'description' => '获得1个临时邀请名额'
        ],
        [
            'name' => '彩虹ID 7天',
            'type' => 'rainbow_id',
            'value' => 7,
            'probability' => 3,
            'description' => '获得7天彩虹ID特权'
        ]
    ]
];
