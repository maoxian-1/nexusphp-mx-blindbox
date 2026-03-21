<?php
require_once("../../../../include/bittorrent.php");
dbconn();
loggedinorreturn();

// 检查管理员权限
if (get_user_class() < UC_ADMINISTRATOR) {
    permissiondenied();
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        // 验证每日免费次数不得超过每日抽奖限制
        $dailyLimit = intval($_POST['daily_limit'] ?? 0);
        $dailyFreeCount = intval($_POST['daily_free_count'] ?? 0);
        if ($dailyLimit > 0 && $dailyFreeCount > $dailyLimit) {
            echo "<script>alert('每日免费抽奖次数不能超过每日抽奖限制！'); history.back();</script>";
            exit;
        }

        // 更新设置
        $settings = [
            'plugin.blindbox.enabled' => $_POST['enabled'] ?? 'no',
            'plugin.blindbox.draw_cost' => intval($_POST['draw_cost']),
            'plugin.blindbox.daily_limit' => $dailyLimit,
            'plugin.blindbox.daily_free_count' => $dailyFreeCount,
            'plugin.blindbox.show_on_torrent' => $_POST['show_on_torrent'] ?? 'no',
        ];

        foreach ($settings as $name => $value) {
            sql_query("INSERT INTO settings (name, value) VALUES (" . sqlesc($name) . ", " . sqlesc($value) . ") ON DUPLICATE KEY UPDATE value = " . sqlesc($value));
        }

        // 清除缓存
        \Nexus\Database\NexusDB::cache_del('nexus_settings_in_laravel');
        \Nexus\Database\NexusDB::cache_del('nexus_settings_in_nexus');

        stdmsg("成功", "设置已更新");

        // 添加自动刷新，避免static变量缓存问题
        echo "<meta http-equiv='refresh' content='1;url={$_SERVER['PHP_SELF']}' />";
    }

    if ($action === 'update_prize' && isset($_POST['prize_id'])) {
        $prize_id = intval($_POST['prize_id']);
        $newProbability = floatval($_POST['probability']);
        $newIsActive = $_POST['is_active'] ? 1 : 0;
        $newType = $_POST['type'] ?? '';

        // 校验概率总和是否超过100%
        // 计算其他启用奖品的概率总和（排除当前编辑的奖品）
        $otherProbability = get_single_value("plugin_blindbox_prizes", "COALESCE(SUM(probability), 0)", "WHERE is_active = 1 AND id != $prize_id");
        $totalProbability = floatval($otherProbability) + ($newIsActive ? $newProbability : 0);
        
        if ($totalProbability > 100) {
            echo "<script>alert(`操作失败：启用奖品的概率总和不能超过100%。\n\n当前其他启用奖品概率总和为 " . number_format($otherProbability, 2) . "%\n您设置的概率为 " . number_format($newProbability, 2) . "%\n总和为 " . number_format($totalProbability, 2) . "%`); history.back();</script>";
            exit;
        }

        // 根据提交后的类型处理奖品值，确保编辑时勋章配置也会被保存
        $value = 0;
        $medal_id = null;
        $medal_bonus = null;
        $value_min = null;
        $value_max = null;

        if ($newType === 'medal') {
            $medal_id = intval($_POST['medal_id'] ?? 0);
            $value = $medal_id;
            $medal_bonus = intval($_POST['medal_bonus'] ?? 100);
        } elseif ($newType === 'upload') {
            $value = floatval($_POST['value']) * 1073741824;
            if (!empty($_POST['value_min']) && !empty($_POST['value_max'])) {
                $value_min = floatval($_POST['value_min']);
                $value_max = floatval($_POST['value_max']);
                $value_min = $value_min * 1073741824;
                $value_max = $value_max * 1073741824;
            }
        } elseif ($newType === 'bonus') {
            $value = floatval($_POST['value']);
            if (!empty($_POST['value_min']) && !empty($_POST['value_max'])) {
                $value_min = intval($_POST['value_min']);
                $value_max = intval($_POST['value_max']);
            }
        } else {
            $value = floatval($_POST['value']);
        }

        $updates = [
            'name' => sqlesc($_POST['name']),
            'description' => sqlesc($_POST['description']),
            'type' => sqlesc($newType),
            'probability' => sqlesc(floatval($_POST['probability'])),
            'value' => sqlesc($value),
            'value_min' => $value_min !== null ? sqlesc($value_min) : 'NULL',
            'value_max' => $value_max !== null ? sqlesc($value_max) : 'NULL',
            'medal_id' => $medal_id !== null ? intval($medal_id) : 'NULL',
            'medal_bonus' => $medal_bonus !== null ? intval($medal_bonus) : 'NULL',
            'daily_limit' => intval($_POST['daily_limit']),
            'total_limit' => intval($_POST['total_limit']),
            'is_active' => $_POST['is_active'] ? 1 : 0,
            'sort_order' => intval($_POST['sort_order']),
        ];

        $set_clause = [];
        foreach ($updates as $key => $val) {
            if ($val === 'NULL') {
                $set_clause[] = "$key = NULL";
            } else {
                $set_clause[] = "$key = $val";
            }
        }

        sql_query("UPDATE plugin_blindbox_prizes SET " . implode(', ', $set_clause) . " WHERE id = $prize_id");
        stdmsg("成功", "奖品已更新");

        // 添加自动刷新，避免static变量缓存问题
        echo "<meta http-equiv='refresh' content='1;url={$_SERVER['PHP_SELF']}' />";
    }

    if ($action === 'add_prize') {
        $newProbability = floatval($_POST['probability']);
        $newIsActive = $_POST['is_active'] ? 1 : 0;

        // 校验概率总和是否超过100%
        if ($newIsActive) {
            $currentTotalProbability = get_single_value("plugin_blindbox_prizes", "COALESCE(SUM(probability), 0)", "WHERE is_active = 1");
            $totalProbability = floatval($currentTotalProbability) + $newProbability;
            
            if ($totalProbability > 100) {
                echo "<script>alert(`操作失败：启用奖品的概率总和不能超过100%。\n\n当前启用奖品概率总和为 " . number_format($currentTotalProbability, 2) . "%\n您设置的概率为 " . number_format($newProbability, 2) . "%\n总和为 " . number_format($totalProbability, 2) . "%`); history.back();</script>";
                exit;
            }
        }

        // 处理不同类型的奖品值
        $value = 0;
        $medal_id = null;
        $medal_bonus = 0;
        $value_min = null;
        $value_max = null;

        if ($_POST['type'] === 'medal') {
            // 勋章类型使用medal_id
            $medal_id = intval($_POST['medal_id']);
            $value = $medal_id; // value字段存储medal_id
            $medal_bonus = intval($_POST['medal_bonus'] ?? 100);
        } elseif ($_POST['type'] === 'upload') {
            // 上传量类型，将GB转换为字节
            $value = floatval($_POST['value']) * 1073741824;
            // 处理随机范围
            if (!empty($_POST['value_min']) && !empty($_POST['value_max'])) {
                $value_min = floatval($_POST['value_min']) * 1073741824;
                $value_max = floatval($_POST['value_max']) * 1073741824;
            }
        } elseif ($_POST['type'] === 'bonus') {
            $value = floatval($_POST['value']);
            // 处理随机范围
            if (!empty($_POST['value_min']) && !empty($_POST['value_max'])) {
                $value_min = intval($_POST['value_min']);
                $value_max = intval($_POST['value_max']);
            }
        } else {
            $value = floatval($_POST['value']);
        }

        // 构建插入语句
        $fields = ['name', 'description', 'type', 'value', 'value_min', 'value_max', 'probability', 'daily_limit', 'total_limit', 'is_active', 'sort_order'];
        $values = [
            sqlesc($_POST['name']),
            sqlesc($_POST['description']),
            sqlesc($_POST['type']),
            sqlesc($value),
            $value_min !== null ? sqlesc($value_min) : 'NULL',
            $value_max !== null ? sqlesc($value_max) : 'NULL',
            sqlesc(floatval($_POST['probability'])),
            intval($_POST['daily_limit']),
            intval($_POST['total_limit']),
            ($_POST['is_active'] ? 1 : 0),
            intval($_POST['sort_order'])
        ];

        if ($_POST['type'] === 'medal') {
            $fields[] = 'medal_id';
            $fields[] = 'medal_bonus';
            $values[] = $medal_id;
            $values[] = $medal_bonus;
        }

        sql_query("INSERT INTO plugin_blindbox_prizes (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")");
        stdmsg("成功", "奖品已添加");

        // 添加自动刷新，避免static变量缓存问题
        echo "<meta http-equiv='refresh' content='1;url={$_SERVER['PHP_SELF']}' />";
    }

    if ($action === 'delete_prize' && isset($_POST['prize_id'])) {
        $prize_id = intval($_POST['prize_id']);
        sql_query("DELETE FROM plugin_blindbox_prizes WHERE id = $prize_id");
        stdmsg("成功", "奖品已删除");

        // 添加自动刷新，避免static变量缓存问题
        echo "<meta http-equiv='refresh' content='1;url={$_SERVER['PHP_SELF']}' />";
    }
}

// 获取统计数据
$stats = [];
$stats['total_draws'] = get_single_value("plugin_blindbox_history", "COUNT(*)");
$stats['today_draws'] = get_single_value("plugin_blindbox_history", "COUNT(*)", "WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
$stats['total_users'] = get_single_value("plugin_blindbox_history", "COUNT(DISTINCT user_id)");
$stats['total_prizes'] = get_single_value("plugin_blindbox_prizes", "COUNT(*)");

// 分页和筛选参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 筛选参数
$filter_user = $_GET['filter_user'] ?? '';
$filter_prize = $_GET['filter_prize'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';

// 构建筛选条件
$where_conditions = [];
if ($filter_user) {
    $filter_user_escaped = mysql_real_escape_string($filter_user);
    $where_conditions[] = "u.username LIKE '%$filter_user_escaped%'";
}
if ($filter_prize) {
    $filter_prize_escaped = mysql_real_escape_string($filter_prize);
    $where_conditions[] = "h.prize_name LIKE '%$filter_prize_escaped%'";
}
if ($filter_type) {
    $filter_type_escaped = mysql_real_escape_string($filter_type);
    $where_conditions[] = "h.prize_type = '$filter_type_escaped'";
}
$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// 获取设置
$settings = [];
$settings['enabled'] = get_setting('plugin.blindbox.enabled', 'yes');
$settings['draw_cost'] = get_setting('plugin.blindbox.draw_cost', '100');
$settings['daily_limit'] = get_setting('plugin.blindbox.daily_limit', '0');
$settings['daily_free_count'] = get_setting('plugin.blindbox.daily_free_count', '1');
$settings['show_on_torrent'] = get_setting('plugin.blindbox.show_on_torrent', 'no');

// 获取奖品列表
$prizes = [];
$res = sql_query("
    SELECT
        p.*,
        COALESCE(today_stats.today_given_count, 0) AS today_given_count
    FROM plugin_blindbox_prizes p
    LEFT JOIN (
        SELECT
            prize_id,
            COUNT(*) AS today_given_count
        FROM plugin_blindbox_history
        WHERE created_at >= CURDATE()
          AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        GROUP BY prize_id
    ) today_stats ON today_stats.prize_id = p.id
    ORDER BY p.sort_order, p.id
");
while ($row = mysql_fetch_assoc($res)) {
    $prizes[] = $row;
}

// 获取最近抽奖记录（带分页和筛选）
$recent_draws = [];

// 检查表是否存在
$table_exists = sql_query("SHOW TABLES LIKE 'plugin_blindbox_history'");
if (!$table_exists || mysql_num_rows($table_exists) == 0) {
    $total_records = 0;
    $total_pages = 1;
} else {
    // 先获取总数
    $count_query = "SELECT COUNT(*) as total FROM plugin_blindbox_history h LEFT JOIN users u ON h.user_id = u.id $where_sql";
    $total_records_res = sql_query($count_query);
    if (!$total_records_res) {
        echo "<!-- SQL Error in count query: " . mysql_error() . " --\u003e";
        echo "<!-- Query: $count_query --\u003e";
        $total_records = 0;
    } else {
        $total_records_row = mysql_fetch_assoc($total_records_res);
        $total_records = $total_records_row['total'];
    }
    $total_pages = max(1, ceil($total_records / $per_page));

    // 获取分页数据
    if ($total_records > 0) {
        $data_query = "SELECT h.*, u.username FROM plugin_blindbox_history h LEFT JOIN users u ON h.user_id = u.id $where_sql ORDER BY h.created_at DESC LIMIT $offset, $per_page";
        $res = sql_query($data_query);
        if (!$res) {
            echo "<!-- SQL Error in data query: " . mysql_error() . " --\u003e";
            echo "<!-- Query: $data_query --\u003e";
        } else {
            while ($row = mysql_fetch_assoc($res)) {
                $recent_draws[] = $row;
            }
        }
    }
}

// 开始输出缓冲，捕获stdhead的输出并修正路径
ob_start();
stdhead("盲盒插件管理");
$header = ob_get_clean();

// 修正相对路径，添加base标签
$header = preg_replace('/(<head[^>]*>)/i', '$1<base href="/" />', $header);

// 输出修正后的头部
echo $header;
?>

<style>
.blindbox-admin {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.section {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

.settings-form {
    margin-top: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: inline-block;
    width: 150px;
    font-weight: bold;
}

.form-group input, .form-group select {
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn {
    padding: 4px 10px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    line-height: 18px;
}

.btn:hover {
    background: #5a67d8;
}

.btn-danger {
    background: #e53e3e;
}

.btn-danger:hover {
    background: #c53030;
}

.warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.pagination {
    margin: 20px 0;
    text-align: center;
}

.pagination a, .pagination span {
    display: inline-block;
    padding: 5px 10px;
    margin: 0 2px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: #5a67d8;
}

.pagination a:hover {
    background: #e9ecef;
}

.pagination .current {
    background: #5a67d8;
    color: white;
    border-color: #5a67d8;
}

.pagination .disabled {
    color: #6c757d;
    pointer-events: none;
}

.filter-form {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-form .form-group {
    display: inline-block;
    margin-right: 15px;
    margin-bottom: 10px;
}

.filter-form .form-group label {
    width: auto;
    margin-right: 5px;
}

.filter-form .form-group input, .filter-form .form-group select {
    width: 150px;
}

/* 弹窗样式 */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-overlay.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #eee;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.modal-close:hover {
    background: rgba(255,255,255,0.3);
}

.modal-body {
    padding: 25px;
}

.modal-body .form-group {
    margin-bottom: 20px;
}

.modal-body .form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}

.modal-body .form-group input,
.modal-body .form-group select,
.modal-body .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    box-sizing: border-box;
}

.modal-body .form-group input:focus,
.modal-body .form-group select:focus,
.modal-body .form-group textarea:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.modal-body .form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.modal-body .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.modal-footer {
    padding: 15px 25px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-footer .btn {
    padding: 10px 25px;
    font-size: 14px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-secondary {
    background: #6c757d;
}

.btn-add-prize {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 6px 10px;
    font-size: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-add-prize:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-edit {
    background: #28a745;
    padding: 4px 8px;
    font-size: 12px;
}

.btn-edit:hover {
    background: #218838;
}
</style>

<script>
function togglePrizeFields(prefix = '') {
    var type = document.getElementById(prefix + 'prize_type').value;
    var valueField = document.getElementById(prefix + 'value_field');
    var medalField = document.getElementById(prefix + 'medal_field');
    var medalBonusField = document.getElementById(prefix + 'medal_bonus_field');
    var randomRangeField = document.getElementById(prefix + 'random_range_field');
    var valueInput = document.getElementById(prefix + 'value_input');
    var valueUnit = document.getElementById(prefix + 'value_unit');
    var rangeUnit = document.getElementById(prefix + 'range_unit');

    // 重置显示
    valueField.style.display = 'block';
    medalField.style.display = 'none';
    medalBonusField.style.display = 'none';
    randomRangeField.style.display = 'none';
    valueUnit.innerHTML = '';
    if (rangeUnit) rangeUnit.innerHTML = '';

    // 根据类型调整
    switch(type) {
        case 'medal':
            valueField.style.display = 'none';
            medalField.style.display = 'block';
            medalBonusField.style.display = 'block';
            valueInput.required = false;
            break;
        case 'upload':
            valueUnit.innerHTML = ' GB';
            if (rangeUnit) rangeUnit.innerHTML = ' GB';
            randomRangeField.style.display = 'block';
            break;
        case 'vip_days':
            valueUnit.innerHTML = ' 天';
            break;
        case 'invite':
            valueUnit.innerHTML = ' 个';
            break;
        case 'bonus':
            valueUnit.innerHTML = ' 魔力值';
            randomRangeField.style.display = 'block';
            break;
        case 'rainbow_id':
            valueUnit.innerHTML = ' 天';
            break;
        case 'attendance_card':
            valueUnit.innerHTML = ' 张';
            break;
        default:
            valueInput.required = true;
    }
}

// 保持筛选条件函数
function preserveFilters(event) {
    // 表单提交时，确保分页参数被重置为第1页
    var pageInput = document.createElement('input');
    pageInput.type = 'hidden';
    pageInput.name = 'page';
    pageInput.value = '1';
    event.target.appendChild(pageInput);
}

// 弹窗相关函数
function openAddModal() {
    document.getElementById('addPrizeModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    togglePrizeFields('add_');
}

function closeAddModal() {
    document.getElementById('addPrizeModal').classList.remove('show');
    document.body.style.overflow = '';
}

function openEditModal(prizeId) {
    // 获取奖品数据
    var prizes = <?php echo json_encode($prizes, JSON_UNESCAPED_UNICODE); ?>;
    var prize = prizes.find(function(p) { return p.id == prizeId; });
    
    if (!prize) return;
    
    // 填充表单
    document.getElementById('edit_prize_id').value = prize.id;
    document.getElementById('edit_name').value = prize.name;
    document.getElementById('edit_description').value = prize.description || '';
    document.getElementById('edit_prize_type').value = prize.type;
    
    // 处理数值
    var value = parseFloat(prize.value);
    if (prize.type === 'upload') {
        value = value / 1073741824; // 转换为GB
    }
    document.getElementById('edit_value_input').value = value;
    
    // 处理随机范围
    var valueMin = prize.value_min ? parseFloat(prize.value_min) : '';
    var valueMax = prize.value_max ? parseFloat(prize.value_max) : '';
    if (prize.type === 'upload' && valueMin) valueMin = valueMin / 1073741824;
    if (prize.type === 'upload' && valueMax) valueMax = valueMax / 1073741824;
    document.getElementById('edit_value_min').value = valueMin;
    document.getElementById('edit_value_max').value = valueMax;
    
    document.getElementById('edit_probability').value = prize.probability;
    document.getElementById('edit_daily_limit').value = prize.daily_limit;
    document.getElementById('edit_total_limit').value = prize.total_limit;
    document.getElementById('edit_sort_order').value = prize.sort_order;
    document.getElementById('edit_is_active').value = (prize.is_active == 1 || prize.is_active === true) ? '1' : '0';
    
    // 勋章相关
    if (prize.type === 'medal') {
        document.getElementById('edit_medal_select').value = prize.medal_id || '';
        document.getElementById('edit_medal_bonus').value = prize.medal_bonus || 100;
    }
    
    // 显示弹窗
    document.getElementById('editPrizeModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    togglePrizeFields('edit_');
}

function closeEditModal() {
    document.getElementById('editPrizeModal').classList.remove('show');
    document.body.style.overflow = '';
}

// 点击遮罩关闭弹窗
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// ESC键关闭弹窗
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
    }
});
</script>

<?php
// 分页函数
function pagination($current_page, $total_pages, $base_url, $params = []) {
    if ($total_pages <= 1) return '';

    $html = '<div class="pagination">';

    // 上一页
    if ($current_page > 1) {
        $params['page'] = $current_page - 1;
        $query_string = http_build_query($params);
        $html .= "<a href='{$base_url}?{$query_string}' class='btn'>上一页</a>";
    } else {
        $html .= '<span class="disabled btn">上一页</span>';
    }

    // 页码
    $start = max(1, $current_page - 3);
    $end = min($total_pages, $current_page + 3);

    if ($start > 1) {
        $params['page'] = 1;
        $query_string = http_build_query($params);
        $html .= "<a href='{$base_url}?{$query_string}' class='btn'>1</a>";
        if ($start > 2) {
            $html .= '<span class="btn" style="background: transparent; border: none;">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $params['page'] = $i;
        $query_string = http_build_query($params);
        if ($i == $current_page) {
            $html .= "<span class='current btn'>$i</span>";
        } else {
            $html .= "<a href='{$base_url}?{$query_string}' class='btn'>$i</a>";
        }
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span class="btn" style="background: transparent; border: none;">...</span>';
        }
        $params['page'] = $total_pages;
        $query_string = http_build_query($params);
        $html .= "<a href='{$base_url}?{$query_string}' class='btn'>$total_pages</a>";
    }

    // 下一页
    if ($current_page < $total_pages) {
        $params['page'] = $current_page + 1;
        $query_string = http_build_query($params);
        $html .= "<a href='{$base_url}?{$query_string}' class='btn'>下一页</a>";
    } else {
        $html .= '<span class="disabled btn">下一页</span>';
    }

    $html .= '</div>';
    return $html;
}
?>

<div class="blindbox-admin">
    <h1>盲盒插件管理</h1>

    <!-- 统计数据 -->
    <div class="section">
        <h2>统计数据</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_draws']); ?></div>
                <div class="stat-label">总抽奖次数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['today_draws']); ?></div>
                <div class="stat-label">今日抽奖</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">参与用户</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_prizes']); ?></div>
                <div class="stat-label">奖品种类</div>
            </div>
        </div>
    </div>

    <!-- 基础设置 -->
    <div class="section">
        <h2>基础设置</h2>
        <form method="POST" class="settings-form">
            <input type="hidden" name="action" value="update_settings">

            <div class="form-group">
                <label>启用盲盒：</label>
                <select name="enabled">
                    <option value="yes" <?php echo $settings['enabled'] === 'yes' ? 'selected' : ''; ?>>启用</option>
                    <option value="no" <?php echo $settings['enabled'] === 'no' ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>

            <div class="form-group">
                <label>抽奖消耗魔力值：</label>
                <input type="number" name="draw_cost" value="<?php echo $settings['draw_cost']; ?>" min="0">
            </div>

            <div class="form-group">
                <label>每日抽奖限制：</label>
                <input type="number" name="daily_limit" value="<?php echo $settings['daily_limit']; ?>" min="0">
                <small style="color: #666;">（0表示不限制）</small>
            </div>

            <div class="form-group">
                <label>每日免费抽奖次数：</label>
                <input type="number" name="daily_free_count" value="<?php echo $settings['daily_free_count']; ?>" min="0">
                <small style="color: #666;">（0表示无免费次数，不能超过每日抽奖限制）</small>
            </div>

            <div class="form-group">
                <label>在种子列表页显示盲盒：</label>
                <select name="show_on_torrent">
                    <option value="yes" <?php echo ($settings['show_on_torrent'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>启用</option>
                    <option value="no" <?php echo ($settings['show_on_torrent'] ?? 'no') === 'no' ? 'selected' : ''; ?>>禁用</option>
                </select>
                <small style="color: #666;">（启用后将在种子列表页面底部显示盲盒入口）</small>
            </div>

            <button type="submit" class="btn">保存设置</button>
        </form>
    </div>

    <!-- 奖品管理 -->
    <div class="section">
        <h2>奖品管理</h2>
        <p style="color: #666; margin-bottom: 15px;">💡 提示：魔力值和上传量类型支持设置随机范围，设置后每次抽奖将在范围内随机发放，概率不足100%时自动按比例抽奖</p>

        <button type="button" class="btn btn-add-prize" onclick="openAddModal()">
            <span style="font-size: 12px;">+</span> 新增奖品
        </button>

        <table class="mainouter" width="100%">
            <thead>
                <tr>
                    <td class="colhead">ID</td>
                    <td class="colhead">名称</td>
                    <td class="colhead">类型</td>
                    <td class="colhead">固定值</td>
                    <td class="colhead">随机范围</td>
                    <td class="colhead">概率(%)</td>
                    <td class="colhead">每日限量</td>
                    <td class="colhead">总限量</td>
                    <td class="colhead">已发放</td>
                    <td class="colhead">今日已发</td>
                    <td class="colhead">状态</td>
                    <td class="colhead">操作</td>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prizes as $prize): ?>
                <tr>
                    <td><?php echo $prize['id']; ?></td>
                    <td><?php echo htmlspecialchars($prize['name']); ?></td>
                    <td>
                        <?php 
                        $typeLabels = [
                            'bonus' => '🎁 魔力值',
                            'upload' => '📤 上传量',
                            'vip_days' => '👑 VIP天数',
                            'invite' => '💌 邀请',
                            'medal' => '🏅 勋章',
                            'rainbow_id' => '🌈 彩虹ID',
                            'attendance_card' => '📅 补签卡'
                        ];
                        echo $typeLabels[$prize['type']] ?? $prize['type'];
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($prize['type'] === 'upload') {
                            echo number_format($prize['value'] / 1073741824, 2) . ' GB';
                        } else {
                            echo number_format($prize['value']);
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($prize['value_min'] && $prize['value_max']) {
                            if ($prize['type'] === 'upload') {
                                echo number_format($prize['value_min'] / 1073741824, 2) . ' - ' . number_format($prize['value_max'] / 1073741824, 2) . ' GB';
                            } else {
                                echo number_format($prize['value_min']) . ' - ' . number_format($prize['value_max']);
                            }
                        } else {
                            echo '<span style="color: #999;">-</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo $prize['probability']; ?>%</td>
                    <td><?php echo $prize['daily_limit'] ?: '不限'; ?></td>
                    <td><?php echo $prize['total_limit'] ?: '不限'; ?></td>
                    <td><?php echo $prize['given_count']; ?></td>
                    <td><?php echo intval($prize['today_given_count'] ?? 0); ?></td>
                    <td>
                        <?php if ($prize['is_active']): ?>
                            <span style="color: #667eea; padding: 2px 5px; background: #e0e7ff; border-radius: 20px; border: 1px solid #667eea;">✓ 启用</span>
                        <?php else: ?>
                            <span style="color: #dc3545; padding: 2px 5px; background: #ffe0e0; border-radius: 20px; border: 1px solid #dc3545;">✗ 禁用</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-primary" onclick="openEditModal(<?php echo $prize['id']; ?>)">编辑</button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_prize">
                            <input type="hidden" name="prize_id" value="<?php echo $prize['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('确定删除该奖品？')">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 最近抽奖记录 -->
    <div class="section">
        <h2>最近抽奖记录</h2>

        <!-- 筛选表单 -->
        <form method="GET" class="filter-form" onsubmit="preserveFilters()">
            <div class="form-group">
                <label>用户：</label>
                <input type="text" name="filter_user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="用户名">
            </div>
            <div class="form-group">
                <label>奖品：</label>
                <input type="text" name="filter_prize" value="<?php echo htmlspecialchars($filter_prize); ?>" placeholder="奖品名称">
            </div>
            <div class="form-group">
                <label>类型：</label>
                <select name="filter_type">
                    <option value="">全部类型</option>
                    <option value="bonus" <?php echo $filter_type === 'bonus' ? 'selected' : ''; ?>>魔力值</option>
                    <option value="upload" <?php echo $filter_type === 'upload' ? 'selected' : ''; ?>>上传量</option>
                    <option value="vip_days" <?php echo $filter_type === 'vip_days' ? 'selected' : ''; ?>>VIP天数</option>
                    <option value="invite" <?php echo $filter_type === 'invite' ? 'selected' : ''; ?>>邀请名额</option>
                    <option value="medal" <?php echo $filter_type === 'medal' ? 'selected' : ''; ?>>勋章</option>
                    <option value="rainbow_id" <?php echo $filter_type === 'rainbow_id' ? 'selected' : ''; ?>>彩虹ID</option>
                    <option value="attendance_card" <?php echo $filter_type === 'attendance_card' ? 'selected' : ''; ?>>补签卡</option>
                </select>
            </div>
            <button type="submit" class="btn">筛选</button>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn" style="font-size: 10pt; color:white; text-decoration: none; display: inline-block;">重置</a>
        </form>

        <!-- 分页信息 -->
        <div style="margin: 15px 0;">
            <p>共 <?php echo $total_records; ?> 条记录，第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页</p>
        </div>

        <!-- 分页导航 -->
        <?php
        $query_params = $_GET;
        unset($query_params['page']);
        echo pagination($page, $total_pages, $_SERVER['PHP_SELF'], $query_params);
        ?>

        <table class="mainouter" width="100%">
            <thead>
                <tr>
                    <td class="colhead">时间</td>
                    <td class="colhead">用户</td>
                    <td class="colhead">奖品</td>
                    <td class="colhead">类型</td>
                    <td class="colhead">数值</td>
                    <td class="colhead">免费</td>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_draws as $draw): ?>
                <tr>
                    <td><?php echo $draw['created_at']; ?></td>
                    <td><?php echo htmlspecialchars($draw['username']); ?></td>
                    <td><?php echo htmlspecialchars($draw['prize_name']); ?></td>
                    <td><?php echo htmlspecialchars($draw['prize_type']); ?></td>
                    <td><?php 
                        if ($draw['prize_type'] === 'upload') {
                            echo number_format($draw['prize_value'] / 1073741824, 2) . ' GB';
                        } else {
                            echo number_format($draw['prize_value']);
                        }
                    ?></td>
                    <td><?php echo $draw['is_free'] ? '是' : '否'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 分页导航 -->
        <?php
        $query_params = $_GET;
        unset($query_params['page']);
        echo pagination($page, $total_pages, $_SERVER['PHP_SELF'], $query_params);
        ?>
    </div>
</div>

<!-- 新增奖品弹窗 -->
<div id="addPrizeModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✨ 新增奖品</h3>
            <button type="button" class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_prize">

                <div class="form-group">
                    <label>奖品名称</label>
                    <input type="text" name="name" placeholder="如：随机魔力值、神秘上传量" required>
                </div>

                <div class="form-group">
                    <label>奖品描述</label>
                    <textarea name="description" rows="2" placeholder="奖品的详细描述，会显示给用户"></textarea>
                </div>

                <div class="form-group">
                    <label>奖品类型</label>
                    <select name="type" id="add_prize_type" required onchange="togglePrizeFields('add_')">
                        <option value="bonus">🎁 魔力值</option>
                        <option value="upload">📤 上传量(GB)</option>
                        <option value="vip_days">👑 VIP天数</option>
                        <option value="invite">💌 邀请名额</option>
                        <option value="medal">🏅 勋章</option>
                        <option value="rainbow_id">🌈 彩虹ID</option>
                        <option value="attendance_card">📅 补签卡</option>
                    </select>
                </div>

                <div class="form-group" id="add_value_field">
                    <label>固定数值</label>
                    <input type="number" name="value" id="add_value_input" step="0.01" required>
                    <span id="add_value_unit"></span>
                </div>

                <div class="form-group" id="add_random_range_field" style="display:none;">
                    <label>随机范围（可选）</label>
                    <div class="form-row">
                        <input type="number" name="value_min" step="0.01" placeholder="最小值">
                        <input type="number" name="value_max" step="0.01" placeholder="最大值">
                    </div>
                    <span id="add_range_unit"></span>
                    <small>设置后将忽略固定数值，每次随机发放范围内的值</small>
                </div>

                <div class="form-group" id="add_medal_field" style="display:none;">
                    <label>选择勋章</label>
                    <select name="medal_id" id="add_medal_select">
                        <option value="">请选择勋章</option>
                        <?php
                        $medals_res = sql_query("SELECT id, name FROM medals ORDER BY name");
                        while ($medal = mysql_fetch_assoc($medals_res)) {
                            echo "<option value='{$medal['id']}'>{$medal['name']} (ID: {$medal['id']})</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" id="add_medal_bonus_field" style="display:none;">
                    <label>已有勋章转换魔力值</label>
                    <input type="number" name="medal_bonus" value="100" min="0">
                    <small>用户已拥有该勋章时获得的魔力值</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>中奖概率(%)</label>
                        <input type="number" name="probability" step="0.01" min="0" max="100" required>
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort_order" value="0" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>每日限量</label>
                        <input type="number" name="daily_limit" value="0" min="0">
                        <small>0为不限</small>
                    </div>
                    <div class="form-group">
                        <label>总限量</label>
                        <input type="number" name="total_limit" value="0" min="0">
                        <small>0为不限</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>状态</label>
                    <select name="is_active">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">取消</button>
                <button type="submit" class="btn btn-primary">添加奖品</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑奖品弹窗 -->
<div id="editPrizeModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✏️ 编辑奖品</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_prize">
                <input type="hidden" name="prize_id" id="edit_prize_id">

                <div class="form-group">
                    <label>奖品名称</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>

                <div class="form-group">
                    <label>奖品描述</label>
                    <textarea name="description" id="edit_description" rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label>奖品类型</label>
                    <select name="type" id="edit_prize_type" required onchange="togglePrizeFields('edit_')">
                        <option value="bonus">🎁 魔力值</option>
                        <option value="upload">📤 上传量(GB)</option>
                        <option value="vip_days">👑 VIP天数</option>
                        <option value="invite">💌 邀请名额</option>
                        <option value="medal">🏅 勋章</option>
                        <option value="rainbow_id">🌈 彩虹ID</option>
                        <option value="attendance_card">📅 补签卡</option>
                    </select>
                </div>

                <div class="form-group" id="edit_value_field">
                    <label>固定数值</label>
                    <input type="number" name="value" id="edit_value_input" step="0.01" required>
                    <span id="edit_value_unit"></span>
                </div>

                <div class="form-group" id="edit_random_range_field" style="display:none;">
                    <label>随机范围（可选）</label>
                    <div class="form-row">
                        <input type="number" name="value_min" id="edit_value_min" step="0.01" placeholder="最小值">
                        <input type="number" name="value_max" id="edit_value_max" step="0.01" placeholder="最大值">
                    </div>
                    <span id="edit_range_unit"></span>
                    <small>设置后将忽略固定数值，每次随机发放范围内的值</small>
                </div>

                <div class="form-group" id="edit_medal_field" style="display:none;">
                    <label>选择勋章</label>
                    <select name="medal_id" id="edit_medal_select">
                        <option value="">请选择勋章</option>
                        <?php
                        $medals_res2 = sql_query("SELECT id, name FROM medals ORDER BY name");
                        while ($medal2 = mysql_fetch_assoc($medals_res2)) {
                            echo "<option value='{$medal2['id']}'>{$medal2['name']} (ID: {$medal2['id']})</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" id="edit_medal_bonus_field" style="display:none;">
                    <label>已有勋章转换魔力值</label>
                    <input type="number" name="medal_bonus" id="edit_medal_bonus" value="100" min="0">
                    <small>用户已拥有该勋章时获得的魔力值</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>中奖概率(%)</label>
                        <input type="number" name="probability" id="edit_probability" step="0.01" min="0" max="100" required>
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort_order" id="edit_sort_order" value="0" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>每日限量</label>
                        <input type="number" name="daily_limit" id="edit_daily_limit" value="0" min="0">
                        <small>0为不限</small>
                    </div>
                    <div class="form-group">
                        <label>总限量</label>
                        <input type="number" name="total_limit" id="edit_total_limit" value="0" min="0">
                        <small>0为不限</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>状态</label>
                    <select name="is_active" id="edit_is_active">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                <button type="submit" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>

</div>

<?php
stdfoot();
?>
