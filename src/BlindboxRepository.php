<?php
namespace NexusPlugin\Blindbox;

use Nexus\Plugin\BasePlugin;
use App\Models\User;
use App\Models\Setting;
use App\Models\Medal;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BlindboxRepository extends BasePlugin
{
    public const ID = 'blindbox';
    public const VERSION = '1.0.0';
    public const COMPATIBLE_NP_VERSION = '1.9.0';

    public function install()
    {
        // 运行数据库迁移
        $this->runMigrations(__DIR__ . '/../database/migrations');
    }

    public function boot()
    {
        // 注册首页悬浮盲盒Hook
        if (function_exists('add_filter')) {
            add_filter('nexus_home_module', [$this, 'injectBlindboxModule'], 10, 1);
        }

        // 注册管理面板
        if (function_exists('add_filter')) {
            add_filter('nexus_admin_menu', [$this, 'addAdminPanelItem'], 10, 1);
        }

        // 记录日志
        if (function_exists('do_log')) {
            do_log('Blindbox plugin booted, filter registered');
        }
    }

    /**
     * 注入首页悬浮盲盒模块
     */
    public function injectBlindboxModule(array $modules): array
    {
        try {
            // 直接使用数据库查询，避免Facade问题
            $enabled = get_setting('plugin.blindbox.enabled', 'yes');

            if (function_exists('do_log')) {
                \do_log('Blindbox module check - enabled: ' . $enabled);
            }

            if ($enabled !== 'yes') {
                return $modules;
            }

            $html = $this->getBlindboxFloatingHtml();
            $modules[] = $html;

            if (function_exists('do_log')) {
                \do_log('Blindbox module injected successfully, total modules: ' . count($modules));
            }
        } catch (\Throwable $e) {
            if (function_exists('do_log')) {
                \do_log('Blindbox module error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            }
        }

        return $modules;
    }

    /**
     * 获取悬浮盲盒HTML
     */
    private function getBlindboxFloatingHtml(): string
    {
        global $CURUSER;

        // 对所有用户显示，包括未登录用户
        $isLoggedIn = $CURUSER ? true : false;

        if ($isLoggedIn) {
            $userId = $CURUSER['id'];
            $freeDrawsToday = $this->getUserFreeDrawsToday($userId);
            $canFreeDraw = get_setting('plugin.blindbox.daily_free', 'yes') === 'yes' && $freeDrawsToday == 0;
            $userBonus = $CURUSER['seedbonus'] ?? 0;
        } else {
            $userId = 0;
            $freeDrawsToday = 0;
            $canFreeDraw = false;
            $userBonus = 0;
        }
        $drawCost = intval(get_setting('plugin.blindbox.draw_cost', '100'));

        $html = <<<HTML
<style>
.blindbox-floating {
    position: fixed;
    right: 20px;
    bottom: 100px;
    z-index: 9999;
    cursor: pointer;
    animation: float 3s ease-in-out infinite;
}

.blindbox-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
}

.blindbox-icon:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 15px 40px rgba(0,0,0,0.4);
}

.blindbox-icon svg {
    width: 50px;
    height: 50px;
    fill: white;
}

.blindbox-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.blindbox-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.blindbox-modal.show {
    display: flex;
}

.blindbox-content {
    background: white;
    border-radius: 20px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
    position: relative;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.blindbox-close {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 30px;
    height: 30px;
    cursor: pointer;
    background: #f1f1f1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.blindbox-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.blindbox-info {
    text-align: center;
    margin-bottom: 20px;
}

.blindbox-balance {
    font-size: 16px;
    margin-bottom: 10px;
}

.blindbox-cost {
    font-size: 14px;
    color: #666;
}

.blindbox-draw-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.blindbox-draw-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}

.blindbox-draw-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.blindbox-draw-btn.free {
    background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%);
}

.blindbox-result {
    display: none;
    text-align: center;
    margin-top: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
}

.blindbox-result.show {
    display: block;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.blindbox-prize {
    font-size: 20px;
    font-weight: bold;
    color: #28a745;
    margin-bottom: 10px;
}

.blindbox-prize-desc {
    font-size: 14px;
    color: #666;
}

.blindbox-history {
    margin-top: 20px;
    max-height: 200px;
    overflow-y: auto;
}

.blindbox-history-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}
</style>

<div class="blindbox-floating" onclick="openBlindbox()">
    <div class="blindbox-icon">
        <svg viewBox="0 0 24 24">
            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
        </svg>
HTML;

        if ($canFreeDraw) {
            $html .= '<div class="blindbox-badge">免</div>';
        }

        $html .= <<<HTML
    </div>
</div>

<div id="blindboxModal" class="blindbox-modal">
    <div class="blindbox-content">
        <div class="blindbox-close" onclick="closeBlindbox()">✕</div>
        <div class="blindbox-title">幸运盲盒</div>

        <div class="blindbox-info">
            <div class="blindbox-balance">当前魔力值: <span id="userBonus">{$userBonus}</span></div>
            <div class="blindbox-cost">每次消耗: <span id="drawCost">{$drawCost}</span> 魔力值</div>
        </div>

HTML;

        if ($canFreeDraw) {
            $html .= '<button class="blindbox-draw-btn free" onclick="drawBlindbox(true)">本次免费抽取</button>';
        } else {
            $btnDisabled = $userBonus < $drawCost ? 'disabled' : '';
            $html .= "<button class='blindbox-draw-btn' onclick='drawBlindbox(false)' {$btnDisabled}>抽取盲盒</button>";
        }

        $html .= <<<HTML

        <div id="blindboxResult" class="blindbox-result">
            <div class="blindbox-prize" id="prizeName"></div>
            <div class="blindbox-prize-desc" id="prizeDesc"></div>
        </div>

        <div class="blindbox-history">
            <div style="font-weight: bold; margin-bottom: 10px;">最近中奖记录</div>
            <div id="historyList"></div>
        </div>
    </div>
</div>

<script>
function openBlindbox() {
    document.getElementById('blindboxModal').classList.add('show');
    loadBlindboxHistory();
}

function closeBlindbox() {
    document.getElementById('blindboxModal').classList.remove('show');
}

function drawBlindbox(isFree) {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '抽奖中...';

    fetch('/plugins/nexusphp-blindbox/api.php?action=draw', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ is_free: isFree })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showPrize(data.prize);
            updateUserBonus(data.new_bonus);
            // if (isFree) {
            //     // 免费抽奖成功后隐藏按钮
            //     btn.style.display = 'none';
            // } else {
            //     // 付费抽奖成功后恢复按钮
            //     btn.disabled = false;
            document.querySelector('.blindbox-badge')?.remove();
            btn.outerHTML = "<button class='blindbox-draw-btn' onclick='drawBlindbox(false)'>抽取盲盒</button>";
            // }
        } else {
            alert(data.message || '抽奖失败');
            // 失败时恢复按钮
            btn.disabled = false;
            btn.textContent = isFree ? '本次免费抽取' : '抽取盲盒';
        }
    })
    .catch(error => {
        alert('网络错误，请重试');
        // 网络错误时恢复按钮
        btn.disabled = false;
        btn.textContent = isFree ? '本次免费抽取' : '抽取盲盒';
    });
}

function showPrize(prize) {
    const resultDiv = document.getElementById('blindboxResult');
    document.getElementById('prizeName').textContent = '恭喜获得: ' + prize.name;
    // 显示实际获得的数值
    document.getElementById('prizeDesc').textContent = '获得: ' + prize.value;
    resultDiv.classList.add('show');

    // 刷新历史记录
    loadBlindboxHistory();
}

function updateUserBonus(newBonus) {
    document.getElementById('userBonus').textContent = newBonus;
}

function loadBlindboxHistory() {
    fetch('/plugins/nexusphp-blindbox/api.php?action=history')
    .then(response => response.json())
    .then(data => {
        const historyList = document.getElementById('historyList');
        historyList.innerHTML = '';

        if (data.history && data.history.length > 0) {
            data.history.forEach(item => {
                const div = document.createElement('div');
                div.className = 'blindbox-history-item';
                div.textContent = item.created_at + ' - ' + item.prize_name;
                historyList.appendChild(div);
            });
        } else {
            historyList.innerHTML = '<div style="color: #999;">暂无记录</div>';
        }
    });
}

// 点击模态框外部关闭
document.getElementById('blindboxModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeBlindbox();
    }
});
</script>
HTML;

        return $html;
    }

    /**
     * 获取用户今日免费抽奖次数
     */
    private function getUserFreeDrawsToday($userId)
    {
        // 使用原生SQL查询，避免Facade问题
        $today = date('Y-m-d');
        $res = sql_query("SELECT COUNT(*) as cnt FROM plugin_blindbox_history WHERE user_id = " . intval($userId) . " AND is_free = 1 AND DATE(created_at) = '$today'");
        $row = mysql_fetch_assoc($res);
        return $row ? intval($row['cnt']) : 0;
    }

    /**
     * 添加管理面板项
     */
    public function addAdminPanelItem($items)
    {
        $items[] = [
            'url' => 'plugins/nexusphp-blindbox/admin.php',
            'name' => '盲盒管理',
            'icon' => 'gift'
        ];
        return $items;
    }
}
