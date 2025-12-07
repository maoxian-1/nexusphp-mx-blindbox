<?php
require_once("../../../include/bittorrent.php");
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
        // 更新设置
        $settings = [
            'plugin.blindbox.enabled' => $_POST['enabled'] ?? 'no',
            'plugin.blindbox.draw_cost' => intval($_POST['draw_cost']),
            'plugin.blindbox.daily_free' => $_POST['daily_free'] ?? 'no',
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

        // 获取奖品类型
        $prize_type_res = sql_query("SELECT type FROM plugin_blindbox_prizes WHERE id = $prize_id");
        $prize_type_row = mysql_fetch_assoc($prize_type_res);
        $prize_type = $prize_type_row['type'];

        // 如果是上传量类型，将GB转换为字节
        $value = floatval($_POST['value']);
        if ($prize_type === 'upload') {
            $value = $value * 1073741824; // GB转字节
        }

        $updates = [
            'name' => sqlesc($_POST['name']),
            'description' => sqlesc($_POST['description']),
            'probability' => sqlesc(floatval($_POST['probability'])),
            'value' => sqlesc($value),
            'daily_limit' => intval($_POST['daily_limit']),
            'total_limit' => intval($_POST['total_limit']),
            'is_active' => $_POST['is_active'] ? 1 : 0,
            'sort_order' => intval($_POST['sort_order']),
        ];

        $set_clause = [];
        foreach ($updates as $key => $value) {
            $set_clause[] = "$key = $value";
        }

        sql_query("UPDATE plugin_blindbox_prizes SET " . implode(', ', $set_clause) . " WHERE id = $prize_id");
        stdmsg("成功", "奖品已更新");

        // 添加自动刷新，避免static变量缓存问题
        echo "<meta http-equiv='refresh' content='1;url={$_SERVER['PHP_SELF']}' />";
    }

    if ($action === 'add_prize') {
        // 处理不同类型的奖品值
        $value = 0;
        $medal_id = null;
        $medal_bonus = 0;

        if ($_POST['type'] === 'medal') {
            // 勋章类型使用medal_id
            $medal_id = intval($_POST['medal_id']);
            $value = $medal_id; // value字段存储medal_id
            $medal_bonus = intval($_POST['medal_bonus'] ?? 100);
        } elseif ($_POST['type'] === 'upload') {
            // 上传量类型，将GB转换为字节
            $value = floatval($_POST['value']) * 1073741824;
        } else {
            $value = floatval($_POST['value']);
        }

        // 构建插入语句
        $fields = ['name', 'description', 'type', 'value', 'probability', 'daily_limit', 'total_limit', 'is_active', 'sort_order'];
        $values = [
            sqlesc($_POST['name']),
            sqlesc($_POST['description']),
            sqlesc($_POST['type']),
            sqlesc($value),
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
$stats['today_draws'] = get_single_value("plugin_blindbox_history", "COUNT(*)", "WHERE DATE(created_at) = CURDATE()");
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
$settings['daily_free'] = get_setting('plugin.blindbox.daily_free', 'yes');

// 获取奖品列表
$prizes = [];
$res = sql_query("SELECT * FROM plugin_blindbox_prizes ORDER BY sort_order, id");
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
</style>

<script>
function togglePrizeFields() {
    var type = document.getElementById('prize_type').value;
    var valueField = document.getElementById('value_field');
    var medalField = document.getElementById('medal_field');
    var medalBonusField = document.getElementById('medal_bonus_field');
    var valueInput = document.getElementById('value_input');
    var valueUnit = document.getElementById('value_unit');

    // 重置显示
    valueField.style.display = 'block';
    medalField.style.display = 'none';
    medalBonusField.style.display = 'none';
    valueUnit.innerHTML = '';

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
            break;
        case 'vip_days':
            valueUnit.innerHTML = ' 天';
            break;
        case 'invite':
            valueUnit.innerHTML = ' 个';
            break;
        case 'bonus':
            valueUnit.innerHTML = ' 魔力值';
            break;
        case 'rainbow_id':
            valueUnit.innerHTML = ' 天';
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
                <label>每日免费抽奖：</label>
                <select name="daily_free">
                    <option value="yes" <?php echo $settings['daily_free'] === 'yes' ? 'selected' : ''; ?>>启用</option>
                    <option value="no" <?php echo $settings['daily_free'] === 'no' ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>

            <button type="submit" class="btn">保存设置</button>
        </form>
    </div>

    <!-- 奖品管理 -->
    <div class="section">
        <h2>奖品管理</h2>

        <table class="mainouter" width="100%">
            <thead>
                <tr>
                    <td class="colhead">ID</td>
                    <td class="colhead">名称</td>
                    <td class="colhead">类型</td>
                    <td class="colhead">数值</td>
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
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="update_prize">
                        <input type="hidden" name="prize_id" value="<?php echo $prize['id']; ?>">
                        <td><?php echo $prize['id']; ?></td>
                        <td><input type="text" name="name" value="<?php echo htmlspecialchars($prize['name']); ?>" style="width: 120px;"></td>
                        <td><?php echo htmlspecialchars($prize['type']); ?></td>
                        <td>
                            <?php if ($prize['type'] === 'upload'): ?>
                                <input type="number" name="value" value="<?php echo $prize['value'] / 1073741824; ?>" step="0.01" style="width: 80px;" title="GB">
                            <?php else: ?>
                                <input type="number" name="value" value="<?php echo $prize['value']; ?>" step="0.01" style="width: 80px;">
                            <?php endif; ?>
                        </td>
                        <td><input type="number" name="probability" value="<?php echo $prize['probability']; ?>" step="0.01" style="width: 60px;"></td>
                        <td><input type="number" name="daily_limit" value="<?php echo $prize['daily_limit']; ?>" style="width: 60px;"></td>
                        <td><input type="number" name="total_limit" value="<?php echo $prize['total_limit']; ?>" style="width: 60px;"></td>
                        <td><?php echo $prize['given_count']; ?></td>
                        <td><?php echo $prize['given_today']; ?></td>
                        <td>
                            <select name="is_active">
                                <option value="1" <?php echo $prize['is_active'] ? 'selected' : ''; ?>>启用</option>
                                <option value="0" <?php echo !$prize['is_active'] ? 'selected' : ''; ?>>禁用</option>
                            </select>
                        </td>
                        <td>
                            <input type="hidden" name="description" value="<?php echo htmlspecialchars($prize['description']); ?>">
                            <input type="hidden" name="sort_order" value="<?php echo $prize['sort_order']; ?>">
                            <button type="submit" class="btn">更新</button>
                            <button type="submit" name="action" value="delete_prize" class="btn btn-danger" onclick="return confirm('确定删除？')">删除</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 添加新奖品 -->
        <h3>添加新奖品</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_prize">

            <div class="form-group">
                <label>奖品名称：</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>奖品描述：</label>
                <input type="text" name="description" style="width: 400px;">
            </div>

            <div class="form-group">
                <label>奖品类型：</label>
                <select name="type" id="prize_type" required onchange="togglePrizeFields()">
                    <option value="bonus">魔力值</option>
                    <option value="upload">上传量(GB)</option>
                    <option value="vip_days">VIP天数</option>
                    <option value="invite">邀请名额</option>
                    <option value="medal">勋章</option>
                    <option value="rainbow_id">彩虹ID</option>
                </select>
            </div>

            <div class="form-group" id="value_field">
                <label>数值：</label>
                <input type="number" name="value" id="value_input" step="0.01" required>
                <span id="value_unit"></span>
            </div>

            <div class="form-group" id="medal_field" style="display:none;">
                <label>选择勋章：</label>
                <select name="medal_id" id="medal_select">
                    <option value="">请选择勋章</option>
                    <?php
                    $medals_res = sql_query("SELECT id, name FROM medals ORDER BY name");
                    while ($medal = mysql_fetch_assoc($medals_res)) {
                        echo "<option value='{$medal['id']}'>{$medal['name']} (ID: {$medal['id']})</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" id="medal_bonus_field" style="display:none;">
                <label>已有勋章转换魔力值：</label>
                <input type="number" name="medal_bonus" value="100" min="0">
                <small>（用户已拥有该勋章时获得的魔力值）</small>
            </div>

            <div class="form-group">
                <label>中奖概率(%)：</label>
                <input type="number" name="probability" step="0.01" min="0" max="100" required>
            </div>

            <div class="form-group">
                <label>每日限量：</label>
                <input type="number" name="daily_limit" value="0" min="0">
                <small>（0为不限）</small>
            </div>

            <div class="form-group">
                <label>总限量：</label>
                <input type="number" name="total_limit" value="0" min="0">
                <small>（0为不限）</small>
            </div>

            <div class="form-group">
                <label>排序：</label>
                <input type="number" name="sort_order" value="0" min="0">
            </div>

            <div class="form-group">
                <label>状态：</label>
                <select name="is_active">
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
            </div>

            <button type="submit" class="btn">添加奖品</button>
        </form>
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
                    <td><?php echo $draw['prize_value']; ?></td>
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

</div>

<?php
stdfoot();
?>
