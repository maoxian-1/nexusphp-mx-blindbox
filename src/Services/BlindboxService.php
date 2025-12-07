<?php

namespace NexusPlugin\Blindbox\Services;

use App\Models\User;
use App\Models\Setting;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BlindboxService
{
    /**
     * 执行抽奖
     */
    public function draw($userId, $isFree = false)
    {
        DB::beginTransaction();
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            // 扣除魔力值
            $cost = 0;
            if (!$isFree) {
                $cost = intval(Setting::get('plugin.blindbox.draw_cost', '100'));
                if ($user->seedbonus < $cost) {
                    throw new \Exception('魔力值不足');
                }
                $user->seedbonus -= $cost;
                $user->save();
            }

            // 获取奖品池
            $prizes = $this->getActivePrizes();
            if (empty($prizes)) {
                throw new \Exception('暂无可用奖品');
            }

            // 抽奖算法
            $prize = $this->drawPrize($prizes);
            if (!$prize) {
                throw new \Exception('抽奖失败');
            }

            // 发放奖品
            $this->givePrize($user, $prize);

            // 记录抽奖历史
            $this->recordHistory($userId, $prize, $isFree, $cost);

            // 更新奖品发放统计
            $this->updatePrizeStatistics($prize->id);

            DB::commit();

            return [
                'success' => true,
                'prize' => [
                    'name' => $prize->name,
                    'description' => $prize->description,
                    'type' => $prize->type,
                    'value' => $prize->value
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Blindbox draw error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查是否可以免费抽奖
     */
    public function canFreeDraw($userId)
    {
        if (Setting::get('plugin.blindbox.daily_free', 'yes') !== 'yes') {
            return false;
        }

        $freeDrawsToday = DB::table('plugin_blindbox_history')
            ->where('user_id', $userId)
            ->where('is_free', 1)
            ->whereDate('created_at', today())
            ->count();

        $dailyFreeTimes = intval(Setting::get('plugin.blindbox.daily_free_times', '1'));
        
        return $freeDrawsToday < $dailyFreeTimes;
    }

    /**
     * 获取活跃的奖品池
     */
    private function getActivePrizes()
    {
        return DB::table('plugin_blindbox_prizes')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('daily_limit', 0)
                    ->orWhere('given_today', '<', DB::raw('daily_limit'));
            })
            ->where(function ($query) {
                $query->where('total_limit', 0)
                    ->orWhere('given_count', '<', DB::raw('total_limit'));
            })
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * 抽奖算法
     */
    private function drawPrize($prizes)
    {
        $totalProbability = $prizes->sum('probability');
        $random = mt_rand(1, $totalProbability * 100) / 100;
        
        $currentProbability = 0;
        foreach ($prizes as $prize) {
            $currentProbability += $prize->probability;
            if ($random <= $currentProbability) {
                return $prize;
            }
        }
        
        // 如果没有命中，返回第一个奖品（保底）
        return $prizes->first();
    }

    /**
     * 发放奖品
     */
    private function givePrize($user, $prize)
    {
        switch ($prize->type) {
            case 'bonus':
                // 发放魔力值
                $user->seedbonus += $prize->value;
                $user->save();
                $this->sendNotification($user->id, "恭喜您获得 {$prize->value} 魔力值！");
                break;
                
            case 'upload':
                // 发放上传量
                $user->uploaded += $prize->value;
                $user->save();
                $uploadGB = number_format($prize->value / 1073741824, 2);
                $this->sendNotification($user->id, "恭喜您获得 {$uploadGB}GB 上传量！");
                break;
                
            case 'vip_days':
                // 发放VIP天数
                $this->giveVipDays($user, $prize->value);
                $this->sendNotification($user->id, "恭喜您获得 {$prize->value} 天VIP会员！");
                break;
                
            case 'invite':
                // 发放邀请名额
                $user->invites += $prize->value;
                $user->save();
                $this->sendNotification($user->id, "恭喜您获得 {$prize->value} 个邀请名额！");
                break;
                
            case 'medal':
                // 发放勋章
                if ($prize->medal_id) {
                    // 检查用户是否已有该勋章
                    $hasMedal = DB::table('user_medals')
                        ->where('user_id', $user->id)
                        ->where('medal_id', $prize->medal_id)
                        ->exists();
                    
                    if ($hasMedal) {
                        // 已有勋章，转换为魔力值
                        $bonusAmount = $prize->medal_bonus ?: 100;
                        $user->seedbonus += $bonusAmount;
                        $user->save();
                        $this->sendNotification($user->id, "您已拥有勋章【{$prize->name}】，已转换为 {$bonusAmount} 魔力值！");
                    } else {
                        // 发放勋章
                        $this->giveMedal($user->id, $prize->medal_id);
                        $this->sendNotification($user->id, "恭喜您获得勋章：{$prize->name}！");
                    }
                }
                break;
                
            case 'rainbow_id':
                // 发放彩虹ID
                $this->giveRainbowId($user, $prize->rainbow_days ?: $prize->value);
                $days = $prize->rainbow_days ?: $prize->value;
                $this->sendNotification($user->id, "恭喜您获得 {$days} 天彩虹ID特权！");
                break;
        }
    }

    /**
     * 发放VIP天数
     */
    private function giveVipDays($user, $days)
    {
        // 获取VIP等级ID（这里假设VIP等级ID为10，实际需要根据系统配置）
        $vipClassId = 10; // 需要根据实际系统配置调整
        
        if ($user->class < $vipClassId) {
            // 如果不是VIP，升级为VIP
            $user->class = $vipClassId;
            $user->vip_added = now();
            $user->vip_until = now()->addDays($days);
        } else {
            // 如果已经是VIP，延长VIP时间
            if ($user->vip_until && $user->vip_until > now()) {
                $user->vip_until = $user->vip_until->addDays($days);
            } else {
                $user->vip_until = now()->addDays($days);
            }
        }
        $user->save();
    }

    /**
     * 发放勋章
     */
    private function giveMedal($userId, $medalId)
    {
        // 检查是否已有该勋章
        $exists = DB::table('user_medals')
            ->where('user_id', $userId)
            ->where('medal_id', $medalId)
            ->exists();
            
        if (!$exists) {
            DB::table('user_medals')->insert([
                'user_id' => $userId,
                'medal_id' => $medalId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * 发放彩虹ID
     */
    private function giveRainbowId($user, $days)
    {
        // 设置彩虹ID（假设有rainbow_id字段）
        if (method_exists($user, 'setRainbowId')) {
            $user->setRainbowId($days);
        } else {
            // 如果没有专门的方法，可以设置一个标记
            $user->rainbow_until = now()->addDays($days);
            $user->save();
        }
    }

    /**
     * 记录抽奖历史
     */
    private function recordHistory($userId, $prize, $isFree, $cost)
    {
        DB::table('plugin_blindbox_history')->insert([
            'user_id' => $userId,
            'prize_id' => $prize->id,
            'prize_name' => $prize->name,
            'prize_type' => $prize->type,
            'prize_value' => $prize->value,
            'is_free' => $isFree,
            'cost' => $cost,
            'ip' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * 更新奖品统计
     */
    private function updatePrizeStatistics($prizeId)
    {
        DB::table('plugin_blindbox_prizes')
            ->where('id', $prizeId)
            ->update([
                'given_count' => DB::raw('given_count + 1'),
                'given_today' => DB::raw('given_today + 1'),
                'updated_at' => now()
            ]);
    }

    /**
     * 发送通知
     */
    private function sendNotification($userId, $message)
    {
        try {
            // 使用系统的消息发送方法
            if (function_exists('send_pm')) {
                send_pm(0, $userId, '盲盒中奖通知', $message);
            } else {
                // 或者直接插入消息表
                DB::table('messages')->insert([
                    'sender' => 0,
                    'receiver' => $userId,
                    'subject' => '盲盒中奖通知',
                    'msg' => $message,
                    'added' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Send notification error: ' . $e->getMessage());
        }
    }

    /**
     * 重置每日统计（可以通过定时任务调用）
     */
    public function resetDailyStatistics()
    {
        DB::table('plugin_blindbox_prizes')->update([
            'given_today' => 0
        ]);
    }
}
