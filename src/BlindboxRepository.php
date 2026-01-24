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

        // 注册种子列表页盲盒Hook（通过全局footer hook）
        if (function_exists('add_action')) {
            add_action('nexus_footer', [$this, 'injectTorrentListModule'], 10);
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
     * 注入种子列表页盲盒模块（通过footer hook）
     */
    public function injectTorrentListModule()
    {
        try {
            // 检查是否启用
            $enabled = get_setting('plugin.blindbox.enabled', 'yes');
            if ($enabled !== 'yes') {
                return;
            }

            // 检查是否在种子列表页显示
            $showOnTorrent = get_setting('plugin.blindbox.show_on_torrent', 'no');
            if ($showOnTorrent !== 'yes') {
                return;
            }

            // 只在种子列表页（torrents.php 和 special.php）显示
            $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
            $allowedPages = ['torrents.php', 'special.php'];
            
            if (!in_array($currentScript, $allowedPages)) {
                return;
            }

            // 输出悬浮盲盒模块（与首页相同）
            echo $this->getBlindboxFloatingHtml();

            if (function_exists('do_log')) {
                \do_log('Blindbox floating module injected on torrent list page: ' . $currentScript);
            }
        } catch (\Throwable $e) {
            if (function_exists('do_log')) {
                \do_log('Blindbox torrent list module error: ' . $e->getMessage());
            }
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

        $dailyLimit = intval(get_setting('plugin.blindbox.daily_limit', '0'));
        $dailyFreeCount = intval(get_setting('plugin.blindbox.daily_free_count', '1'));

        if ($isLoggedIn) {
            $userId = $CURUSER['id'];
            $todayDraws = $this->getUserDrawsToday($userId);
            $freeDrawsToday = $todayDraws['free'];
            $totalDrawsToday = $todayDraws['total'];
            $freeRemaining = max(0, $dailyFreeCount - $freeDrawsToday);
            $canFreeDraw = $freeRemaining > 0 && ($dailyLimit == 0 || $totalDrawsToday < $dailyLimit);
            $userBonus = $CURUSER['seedbonus'] ?? 0;
        } else {
            $userId = 0;
            $freeDrawsToday = 0;
            $totalDrawsToday = 0;
            $freeRemaining = 0;
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
    font-size: 60px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
}

.blindbox-icon.exhausted {
    opacity: 0.5;
    filter: grayscale(50%);
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

.blindbox-limit {
    font-size: 14px;
    color: #e74c3c;
    margin-top: 8px;
}

.blindbox-free-info {
    font-size: 14px;
    color: #27ae60;
    margin-top: 5px;
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

HTML;

        // 检查是否达到每日限制（用于图标样式）
        $iconExhausted = $dailyLimit > 0 && $totalDrawsToday >= $dailyLimit;
        $exhaustedClass = $iconExhausted ? ' exhausted' : '';

        $html .= <<<HTML
<div class="blindbox-floating" onclick="openBlindbox()">
    <div class="blindbox-icon{$exhaustedClass}" id="blindboxIcon">
        <!-- <svg t="1769260998644" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2189" width="200" height="200"><path d="M319 125.748l-22 36-3.333 38.666 12.667 34 18 24.69 140 11.31H715l12-35.333 10.667-50-16.667-46-48-26.667-50-5.333-56.666 38-14 34.667-19 19L521 210.414l-18.666-18.666-54.667-42-29.412-32.667-35.255-10h-44z" fill="#F4DD50" p-id="2190"></path><path d="M171 599.122V411.82c0-16.016 12.984-29 29-29h624c16.016 0 29 12.984 29 29v479.978c0 16.016-12.984 29-29 29H200c-16.016 0-29-12.984-29-29V667.122" fill="#E7402E" p-id="2191"></path><path d="M824 935.798H200c-24.262 0-44-19.738-44-44V667.122c0-8.284 6.716-15 15-15s15 6.716 15 15v224.676c0 7.72 6.28 14 14 14h624c7.72 0 14-6.28 14-14V411.82c0-7.72-6.28-14-14-14H200c-7.72 0-14 6.28-14 14v187.302c0 8.284-6.716 15-15 15s-15-6.716-15-15V411.82c0-24.262 19.738-44 44-44h624c24.262 0 44 19.738 44 44v479.978c0 24.262-19.738 44-44 44zM715.507 111.064c-42.928-35.238-106.509-29.041-141.799 13.865l-54.361 66.147-69.726-66.934c-35.378-42.198-98.473-48.118-141.134-13.078-42.909 35.268-49.117 98.883-13.846 141.799a16.557 16.557 0 0 0 11.205 6.003 16.575 16.575 0 0 0 12.159-3.712c7.082-5.818 8.108-16.278 2.291-23.364-23.64-28.831-19.496-71.355 9.272-95.076 28.834-23.633 71.359-19.477 95.069 9.297 0.407 0.498 0.851 0.973 1.326 1.429l72.245 69.369-12.44 15.125c-5.818 7.087-4.791 17.546 2.291 23.364 7.086 5.817 17.546 4.791 23.364-2.292l87.948-106.996c23.63-28.774 66.283-32.923 95.069-9.297 28.77 23.717 32.92 66.249 9.271 95.076a16.6 16.6 0 0 0-2.88 16.482 16.6 16.6 0 0 0 28.535 4.59c35.27-42.913 29.049-106.529-13.859-141.797z m0 0" fill="#3D100B" p-id="2192"></path><path d="M418.255 471.645h187.491v434.152H418.255z" fill="#F4DD50" p-id="2193"></path><path d="M252.513 470.458H126.005c-16.016 0-29-12.984-29-29v-148c0-16.016 12.984-29 29-29h771.991c16.016 0 29 12.984 29 29v148c0 16.016-12.984 29-29 29H329.513" fill="#E7402E" p-id="2194"></path><path d="M897.995 485.457H329.512c-8.284 0-15-6.716-15-15s6.716-15 15-15h568.483c7.72 0 14-6.28 14-14v-148c0-7.72-6.28-14-14-14h-771.99c-7.72 0-14 6.28-14 14v148c0 7.72 6.28 14 14 14h126.508c8.284 0 15 6.716 15 15s-6.716 15-15 15H126.005c-24.262 0-44-19.738-44-44v-148c0-24.262 19.738-44 44-44h771.991c24.262 0 44 19.738 44 44v148c-0.001 24.262-19.739 44-44.001 44z" fill="#3D100B" p-id="2195"></path><path d="M767.078 336.64h-206.99c-8.284 0-15-6.716-15-15s6.716-15 15-15h206.99c8.284 0 15 6.716 15 15s-6.716 15-15 15zM860.073 336.64h-40.498c-8.284 0-15-6.716-15-15s6.716-15 15-15h40.498c8.284 0 15 6.716 15 15s-6.715 15-15 15z" fill="#FFFFFF" p-id="2196"></path></svg> -->
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
HTML;

        // 显示每日限制信息
        if ($dailyLimit > 0) {
            $remainingDraws = max(0, $dailyLimit - $totalDrawsToday);
            $html .= "<div class='blindbox-limit'>今日剩余: <span>{$remainingDraws}</span> / {$dailyLimit} 次</div>";
        }
        
        // 显示免费次数信息
        if ($dailyFreeCount > 0) {
            $html .= "<div class='blindbox-free-info'>免费次数: <span id='freeRemaining'>{$freeRemaining}</span> / {$dailyFreeCount} 次</div>";
        }

        $html .= <<<HTML
        </div>

HTML;

        // 检查是否达到每日限制
        $reachedDailyLimit = $dailyLimit > 0 && $totalDrawsToday >= $dailyLimit;
        
        if ($reachedDailyLimit) {
            $html .= '<button class="blindbox-draw-btn" disabled>今日次数已用完</button>';
        } elseif ($canFreeDraw) {
            $html .= '<button class="blindbox-draw-btn free" onclick="drawBlindbox(true)">免费抽取 (剩余' . $freeRemaining . '次)</button>';
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
            
            // 更新图标状态和按钮
            updateDrawStatus(data.remaining_draws, data.free_remaining, data.daily_limit);
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

function updateDrawStatus(remainingDraws, freeRemaining, dailyLimit) {
    const icon = document.getElementById('blindboxIcon');
    const limitSpan = document.querySelector('.blindbox-limit span');
    const freeSpan = document.getElementById('freeRemaining');
    
    // 更新剩余次数显示
    if (limitSpan && dailyLimit > 0) {
        limitSpan.textContent = remainingDraws;
    }
    if (freeSpan) {
        freeSpan.textContent = freeRemaining;
    }
    
    // 检查是否达到每日限制
    if (dailyLimit > 0 && remainingDraws <= 0) {
        // 图标变灰
        icon.classList.add('exhausted');
        // 按钮禁用
        const drawBtn = document.querySelector('.blindbox-draw-btn');
        if (drawBtn) {
            drawBtn.outerHTML = '<button class="blindbox-draw-btn" disabled>今日次数已用完</button>';
        }
    } else if (freeRemaining > 0) {
        // 还有免费次数
        const drawBtn = document.querySelector('.blindbox-draw-btn');
        if (drawBtn) {
            drawBtn.outerHTML = '<button class="blindbox-draw-btn free" onclick="drawBlindbox(true)">免费抽取 (剩余' + freeRemaining + '次)</button>';
        }
    } else {
        // 没有免费次数，使用付费抽奖
        const drawBtn = document.querySelector('.blindbox-draw-btn');
        if (drawBtn) {
            drawBtn.outerHTML = '<button class="blindbox-draw-btn" onclick="drawBlindbox(false)">抽取盲盒</button>';
        }
    }
}

function showPrize(prize) {
    const resultDiv = document.getElementById('blindboxResult');
    document.getElementById('prizeName').textContent = '恭喜获得: ' + prize.name;
    // 显示实际获得的数值
    let displayText = '获得: ' + prize.value;
    if (prize.extra_message) {
        displayText += '\\n' + prize.extra_message;
    }
    document.getElementById('prizeDesc').textContent = displayText;
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
                const freeTag = item.is_free == 1 ? '<span style="color:#3a7bd5;font-size:12px;">[免费]</span> ' : '';
                div.innerHTML = '<span style="color:#999;font-size:12px;">' + item.created_at + '</span><br>' + freeTag + item.prize_name + ' <span style="color:#28a745;font-weight:bold;">(' + item.prize_value + ')</span>';
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
     * 获取用户今日抽奖次数
     */
    private function getUserDrawsToday($userId)
    {
        // 使用原生SQL查询，避免Facade问题
        $today = date('Y-m-d');
        $res = sql_query("SELECT COUNT(*) as total, SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_count FROM plugin_blindbox_history WHERE user_id = " . intval($userId) . " AND DATE(created_at) = '$today'");
        $row = mysql_fetch_assoc($res);
        return [
            'total' => $row ? intval($row['total']) : 0,
            'free' => $row ? intval($row['free_count']) : 0
        ];
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
