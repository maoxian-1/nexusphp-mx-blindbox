<?php
require_once("../../../../include/bittorrent.php");
dbconn();

// 设置JSON响应头
header('Content-Type: application/json');

// 获取操作类型
$action = $_GET['action'] ?? '';

// 需要登录的操作
$requireLogin = ['draw', 'history'];
if (in_array($action, $requireLogin)) {
    loggedinorreturn();
}

// 抽奖接口
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'draw') {
    // 使用NexusPHP的认证
    global $CURUSER;

    $drawCost = intval(get_setting('plugin.blindbox.draw_cost', '100'));
    $dailyLimit = intval(get_setting('plugin.blindbox.daily_limit', '0'));
    $dailyFreeCount = intval(get_setting('plugin.blindbox.daily_free_count', '1'));

    if (!$CURUSER) {
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }

    // 获取请求数据
    $input = json_decode(file_get_contents('php://input'), true);
    $isFree = $input['is_free'] ?? false;

    // 获取今日抽奖次数
    $today = date('Y-m-d');
    $res = sql_query("SELECT COUNT(*) as total, SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_count FROM plugin_blindbox_history WHERE user_id = " . $CURUSER['id'] . " AND DATE(created_at) = '$today'");
    $row = mysql_fetch_assoc($res);
    $todayTotal = $row ? intval($row['total']) : 0;
    $todayFreeUsed = $row ? intval($row['free_count']) : 0;

    // 检查每日抽奖限制
    if ($dailyLimit > 0 && $todayTotal >= $dailyLimit) {
        echo json_encode(['success' => false, 'message' => '今日抽奖次数已达上限（' . $dailyLimit . '次）']);
        exit;
    }

    // 检查免费次数
    if ($isFree) {
        if ($dailyFreeCount <= 0) {
            echo json_encode(['success' => false, 'message' => '当前未开放免费抽奖']);
            exit;
        }
        if ($todayFreeUsed >= $dailyFreeCount) {
            echo json_encode(['success' => false, 'message' => '今日免费次数已用完（每日' . $dailyFreeCount . '次）']);
            exit;
        }
    } else {
        // 检查魔力值
        if ($CURUSER['seedbonus'] < $drawCost) {
            echo json_encode(['success' => false, 'message' => '魔力值不足']);
            exit;
        }
    }

    // 执行抽奖 - 根据概率选择奖品
    // 排除已达到限量的奖品：
    // 1. daily_limit > 0 且 given_today >= daily_limit 的奖品（今日已达限量）
    // 2. total_limit > 0 且 given_count >= total_limit 的奖品（总数已达限量）
    $prizes = sql_query("SELECT * FROM plugin_blindbox_prizes WHERE is_active = 1 AND (daily_limit = 0 OR given_today < daily_limit) AND (total_limit = 0 OR given_count < total_limit)");
    $prizeList = [];
    $totalWeight = 0;

    while ($row = mysql_fetch_assoc($prizes)) {
        $prizeList[] = $row;
        $totalWeight += $row['probability'];
    }

    if (empty($prizeList)) {
        echo json_encode(['success' => false, 'message' => '当前没有可用的奖品，请稍后再试']);
        exit;
    }

    // 随机选择奖品
    $random = mt_rand(1, $totalWeight * 100) / 100;
    $currentWeight = 0;
    $selectedPrize = null;

    foreach ($prizeList as $prize) {
        $currentWeight += $prize['probability'];
        if ($random <= $currentWeight) {
            $selectedPrize = $prize;
            break;
        }
    }

    if (!$selectedPrize) {
        $selectedPrize = $prizeList[array_rand($prizeList)];
    }

    // 计算实际发放的数值（支持随机范围）
    $actualValue = floatval($selectedPrize['value']);
    
    // 如果设置了随机范围，且类型是魔力值或上传量，则使用随机值
    if (in_array($selectedPrize['type'], ['bonus', 'upload']) 
        && !empty($selectedPrize['value_min']) 
        && !empty($selectedPrize['value_max'])) {
        
        $minVal = floatval($selectedPrize['value_min']);
        $maxVal = floatval($selectedPrize['value_max']);
        
        if ($selectedPrize['type'] === 'bonus') {
            // 魔力值使用整数随机
            $actualValue = mt_rand(intval($minVal), intval($maxVal));
        } else {
            // 上传量使用浮点数随机（精确到0.01GB）
            $actualValue = $minVal + (mt_rand(0, 10000) / 10000) * ($maxVal - $minVal);
            $actualValue = round($actualValue); // 转为整数字节
        }
    }

    // 记录历史（使用实际发放的值）
    sql_query("INSERT INTO plugin_blindbox_history (user_id, prize_id, prize_name, prize_type, prize_value, is_free, cost, ip, created_at) VALUES (" .
        $CURUSER['id'] . ", " . $selectedPrize['id'] . ", " . sqlesc($selectedPrize['name']) . ", " . sqlesc($selectedPrize['type']) . ", " .
        sqlesc($actualValue) . ", " . ($isFree ? 1 : 0) . ", " . ($isFree ? 0 : $drawCost) . ", " . sqlesc(getip()) . ", NOW())");

    // 更新奖品发放统计
    sql_query("UPDATE plugin_blindbox_prizes SET given_count = given_count + 1, given_today = given_today + 1 WHERE id = " . $selectedPrize['id']);

    // 扣除魔力值（如果不是免费）
    if (!$isFree) {
        
        sql_query("UPDATE users SET seedbonus = seedbonus - $drawCost WHERE id = " . $CURUSER['id']);
    }

    // 发放奖品（使用实际计算的值）
    switch ($selectedPrize['type']) {
        case 'bonus':
            sql_query("UPDATE users SET seedbonus = seedbonus + " . $actualValue . " WHERE id = " . $CURUSER['id']);
            break;
        case 'upload':
            sql_query("UPDATE users SET uploaded = uploaded + " . $actualValue . " WHERE id = " . $CURUSER['id']);
            break;
        case 'vip_days':
            $vipUntil = date('Y-m-d H:i:s', strtotime('+' . intval($selectedPrize['value']) . ' days'));
            sql_query("UPDATE users SET class = " . UC_VIP . ", vip_until = '$vipUntil' WHERE id = " . $CURUSER['id'] . " AND class < " . UC_VIP);
            break;
        case 'invite':
            sql_query("UPDATE users SET invites = invites + " . intval($selectedPrize['value']) . " WHERE id = " . $CURUSER['id']);
            break;
        case 'medal':
            $medalConverted = false;
            $medalConvertedBonus = 0;
            if ($selectedPrize['medal_id']) {
                // 检查是否已有勋章
                $hasMedal = get_single_value("user_medals", "COUNT(*)", "WHERE uid = " . $CURUSER['id'] . " AND medal_id = " . $selectedPrize['medal_id']);
                if ($hasMedal) {
                    // 转换为魔力值
                    $bonusAmount = $selectedPrize['medal_bonus'] ?: 100;
                    sql_query("UPDATE users SET seedbonus = seedbonus + $bonusAmount WHERE id = " . $CURUSER['id']);
                    $medalConverted = true;
                    $medalConvertedBonus = $bonusAmount;
                } else {
                    // 发放勋章
                    sql_query("INSERT INTO user_medals (uid, medal_id, status) VALUES (" . $CURUSER['id'] . ", " . $selectedPrize['medal_id'] . ", 0)");
                }
            }
            break;
        case 'rainbow_id':
            $days = intval($selectedPrize['value']);
            // 通过user_meta表添加彩虹ID权限
            $user = \App\Models\User::query()->findOrFail($CURUSER['id']);
            $userRep = new \App\Repositories\UserRepository();
            $metaData = [
                'meta_key' => \App\Models\UserMeta::META_KEY_PERSONALIZED_USERNAME,
                'duration' => $days,
            ];
            $userRep->addMeta($user, $metaData, $metaData, false);
            break;
        case 'attendance_card':
            // 增加补签卡
            sql_query("UPDATE users SET attendance_card = attendance_card + " . intval($actualValue) . " WHERE id = " . $CURUSER['id']);
            break;
    }

    // 获取新的魔力值
    $userRes = sql_query("SELECT seedbonus FROM users WHERE id = " . $CURUSER['id']);
    $userRow = mysql_fetch_assoc($userRes);
    $newBonus = $userRow['seedbonus'];

    // 格式化显示的数值（使用实际发放的值）
    $displayValue = $actualValue;
    $extraMessage = '';
    if ($selectedPrize['type'] === 'upload') {
        $displayValue = round($actualValue / 1073741824, 2) . ' GB上传量';
    } elseif ($selectedPrize['type'] === 'vip_days') {
        $displayValue = intval($actualValue) . ' 天VIP';
    } elseif ($selectedPrize['type'] === 'rainbow_id') {
        $displayValue = intval($actualValue) . ' 天彩虹ID';
    } elseif ($selectedPrize['type'] === 'bonus') {
        $displayValue = number_format($actualValue) . ' 魔力值';
    } elseif ($selectedPrize['type'] === 'invite') {
        $displayValue = intval($actualValue) . ' 个邀请名额';
    } elseif ($selectedPrize['type'] === 'medal') {
        if (isset($medalConverted) && $medalConverted) {
            $displayValue = '勋章（已拥有，转换为 ' . number_format($medalConvertedBonus) . ' 魔力值）';
            $extraMessage = '您已拥有该勋章，已自动转换为 ' . number_format($medalConvertedBonus) . ' 魔力值';
        } else {
            $displayValue = '勋章';
        }
    } elseif ($selectedPrize['type'] === 'attendance_card') {
        $displayValue = intval($actualValue) . ' 张补签卡';
    }

    // 计算抽奖后的剩余次数
    $remainingDraws = 0;
    if ($dailyLimit > 0) {
        $remainingDraws = max(0, $dailyLimit - $todayTotal - 1); // -1 因为刚抽了一次
    } else {
        $remainingDraws = -1; // -1 表示不限制
    }

    // 计算剩余免费次数
    $freeRemaining = 0;
    if ($dailyFreeCount > 0) {
        $newFreeUsed = $isFree ? $todayFreeUsed + 1 : $todayFreeUsed;
        $freeRemaining = max(0, $dailyFreeCount - $newFreeUsed);
    }

    echo json_encode([
        'success' => true,
        'prize' => [
            'name' => $selectedPrize['name'],
            'description' => $selectedPrize['description'],
            'type' => $selectedPrize['type'],
            'value' => $displayValue,
            'extra_message' => $extraMessage
        ],
        'new_bonus' => $newBonus,
        'remaining_draws' => $remainingDraws,
        'free_remaining' => $freeRemaining,
        'daily_limit' => $dailyLimit
    ]);
    exit;
}

// 获取历史记录
if ($action === 'history') {
    $history = [];
    $res = sql_query("SELECT * FROM plugin_blindbox_history WHERE user_id = " . $CURUSER['id'] . " ORDER BY created_at DESC LIMIT 10");
    while ($row = mysql_fetch_assoc($res)) {
        // 格式化显示的数值
        $displayValue = $row['prize_value'];
        if ($row['prize_type'] === 'upload') {
            $displayValue = round($row['prize_value'] / 1073741824, 2) . ' GB上传量';
        } elseif ($row['prize_type'] === 'vip_days') {
            $displayValue = $row['prize_value'] . ' 天VIP';
        } elseif ($row['prize_type'] === 'rainbow_id') {
            $displayValue = $row['prize_value'] . ' 天彩虹ID';
        } elseif ($row['prize_type'] === 'bonus') {
            $displayValue = $row['prize_value'] . ' 魔力值';
        } elseif ($row['prize_type'] === 'invite') {
            $displayValue = $row['prize_value'] . ' 个邀请名额';
        }

        $history[] = [
            'prize_name' => $row['prize_name'],
            'prize_type' => $row['prize_type'],
            'prize_value' => $displayValue,
            'is_free' => $row['is_free'],
            'created_at' => date('Y-m-d H:i', strtotime($row['created_at']))
        ];
    }
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

// 获取奖品列表
if ($action === 'prizes') {
    $prizes = [];
    $res = sql_query("SELECT name, description, type, probability FROM plugin_blindbox_prizes WHERE is_active = 1 ORDER BY sort_order");
    while ($row = mysql_fetch_assoc($res)) {
        $prizes[] = $row;
    }
    echo json_encode(['success' => true, 'prizes' => $prizes]);
    exit;
}

echo json_encode(['success' => false, 'message' => '无效的请求']);
?>
