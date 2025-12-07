<?php

namespace NexusPlugin\Blindbox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Setting;
use NexusPlugin\Blindbox\Services\BlindboxService;

class BlindboxController extends Controller
{
    protected $blindboxService;

    public function __construct(BlindboxService $blindboxService)
    {
        $this->blindboxService = $blindboxService;
    }

    /**
     * 抽奖接口
     */
    public function draw(Request $request)
    {
        try {
            // 使用NexusPHP的认证系统
            global $CURUSER;
            if (!$CURUSER) {
                return response()->json([
                    'success' => false,
                    'message' => '请先登录'
                ], 401);
            }
            
            $user = User::find($CURUSER['id']);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '用户不存在'
                ], 401);
            }

            $isFree = $request->input('is_free', false);
            
            // 检查是否可以免费抽奖
            if ($isFree) {
                if (!$this->blindboxService->canFreeDraw($user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => '今日免费次数已用完'
                    ]);
                }
            } else {
                // 检查魔力值是否足够
                $drawCost = intval(Setting::get('plugin.blindbox.draw_cost', '100'));
                if ($user->seedbonus < $drawCost) {
                    return response()->json([
                        'success' => false,
                        'message' => '魔力值不足'
                    ]);
                }
            }

            // 执行抽奖
            $result = $this->blindboxService->draw($user->id, $isFree);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'prize' => $result['prize'],
                    'new_bonus' => $user->fresh()->seedbonus,
                    'message' => '恭喜中奖！'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? '抽奖失败'
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Blindbox draw error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '系统错误，请稍后重试'
            ], 500);
        }
    }

    /**
     * 获取用户抽奖历史
     */
    public function history(Request $request)
    {
        try {
            // 使用NexusPHP的认证系统
            global $CURUSER;
            if (!$CURUSER) {
                return response()->json([
                    'success' => false,
                    'message' => '请先登录'
                ], 401);
            }
            
            $user = User::find($CURUSER['id']);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '用户不存在'
                ], 401);
            }

            $history = DB::table('plugin_blindbox_history')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['prize_name', 'prize_type', 'prize_value', 'is_free', 'created_at'])
                ->map(function ($item) {
                    $item->created_at = date('Y-m-d H:i', strtotime($item->created_at));
                    return $item;
                });

            return response()->json([
                'success' => true,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            \Log::error('Blindbox history error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取历史记录失败'
            ], 500);
        }
    }

    /**
     * 获取奖品列表
     */
    public function prizes(Request $request)
    {
        try {
            $prizes = DB::table('plugin_blindbox_prizes')
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get(['name', 'description', 'type', 'probability']);

            return response()->json([
                'success' => true,
                'prizes' => $prizes
            ]);
        } catch (\Exception $e) {
            \Log::error('Blindbox prizes error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取奖品列表失败'
            ], 500);
        }
    }
}
