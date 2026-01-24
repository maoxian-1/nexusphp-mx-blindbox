<?php

return [
    'title' => '幸运盲盒',
    'draw' => '抽取盲盒',
    'free_draw' => '免费抽取',
    'current_bonus' => '当前魔力值',
    'draw_cost' => '每次消耗',
    'bonus_unit' => '魔力值',
    'congratulations' => '恭喜获得',
    'history' => '最近中奖记录',
    'no_history' => '暂无记录',
    'insufficient_bonus' => '魔力值不足',
    'free_used' => '今日免费次数已用完',
    'draw_failed' => '抽奖失败',
    'network_error' => '网络错误，请重试',
    'drawing' => '抽奖中...',
    'please_login' => '请先登录',
    'try_again' => '再来一次',
    'login_to_participate' => '登录后参与',
    'try_your_luck' => '试试手气，赢取丰厚奖励',
    
    // 奖品类型
    'prize_types' => [
        'bonus' => '魔力值',
        'upload' => '上传量',
        'vip_days' => 'VIP天数',
        'invite' => '邀请名额',
        'medal' => '勋章',
        'rainbow_id' => '彩虹ID',
    ],
    
    // 随机奖励
    'random' => [
        'range' => '范围',
        'min_value' => '最小值',
        'max_value' => '最大值',
        'random_mode' => '随机范围模式',
        'fixed_mode' => '固定数值模式',
        'random_hint' => '设置最小值和最大值后，每次抽奖将在范围内随机',
        'only_bonus_upload' => '仅对魔力值和上传量类型生效',
    ],
    
    // 通知消息
    'notification' => [
        'title' => '盲盒中奖通知',
        'bonus' => '恭喜您获得 :value 魔力值！',
        'upload' => '恭喜您获得 :value GB 上传量！',
        'vip_days' => '恭喜您获得 :value 天VIP会员！',
        'invite' => '恭喜您获得 :value 个邀请名额！',
        'medal' => '恭喜您获得勋章：:name！',
        'rainbow_id' => '恭喜您获得 :value 天彩虹ID特权！',
    ],
    
    // 管理界面
    'admin' => [
        'title' => '盲盒管理',
        'prize_management' => '奖品管理',
        'history' => '抽奖记录',
        'settings' => '盲盒设置',
        'statistics' => '统计数据',
        'global_settings' => '全局设置',
        'reset_daily' => '重置今日统计',
        'add_prize' => '添加奖品',
        'enabled' => '启用盲盒功能',
        'daily_free_enabled' => '启用每日免费抽奖',
        'show_on_torrent' => '在种子列表页显示盲盒',
        'total_draws' => '总抽奖次数',
        'today_draws' => '今日抽奖',
        'participated_users' => '参与用户',
        'active_prizes' => '活跃奖品',
        'probability_sum' => '概率总和',
    ],
];
