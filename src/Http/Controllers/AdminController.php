<?php

namespace NexusPlugin\Blindbox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use NexusPlugin\Blindbox\Models\BlindboxPrize;

class AdminController extends Controller
{
    /**
     * 管理页面
     */
    public function index()
    {
        // 检查权限
        if (!auth()->user() || !auth()->user()->isStaffMember()) {
            abort(403, 'Unauthorized');
        }

        $prizes = BlindboxPrize::orderBy('sort_order')->get();
        $totalProbability = $prizes->sum('probability');
        
        return view('blindbox::admin.index', compact('prizes', 'totalProbability'));
    }

    /**
     * 更新奖品
     */
    public function updatePrize(Request $request, $id)
    {
        // 检查权限
        if (!auth()->user() || !auth()->user()->isStaffMember()) {
            abort(403, 'Unauthorized');
        }

        $prize = BlindboxPrize::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'probability' => 'required|numeric|min:0|max:100',
            'value' => 'required|numeric|min:0',
            'daily_limit' => 'integer|min:0',
            'total_limit' => 'integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $prize->update($validated);

        return redirect()->back()->with('success', '奖品更新成功');
    }

    /**
     * 创建奖品
     */
    public function createPrize(Request $request)
    {
        // 检查权限
        if (!auth()->user() || !auth()->user()->isStaffMember()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:bonus,upload,vip_days,invite,medal,rainbow_id',
            'probability' => 'required|numeric|min:0|max:100',
            'value' => 'required|numeric|min:0',
            'medal_id' => 'nullable|string',
            'rainbow_days' => 'integer|min:0',
            'daily_limit' => 'integer|min:0',
            'total_limit' => 'integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        BlindboxPrize::create($validated);

        return redirect()->back()->with('success', '奖品创建成功');
    }

    /**
     * 删除奖品
     */
    public function deletePrize($id)
    {
        // 检查权限
        if (!auth()->user() || !auth()->user()->isStaffMember()) {
            abort(403, 'Unauthorized');
        }

        $prize = BlindboxPrize::findOrFail($id);
        $prize->delete();

        return redirect()->back()->with('success', '奖品删除成功');
    }

    /**
     * 查看抽奖历史
     */
    public function history(Request $request)
    {
        // 检查权限
        if (!auth()->user() || !auth()->user()->isStaffMember()) {
            abort(403, 'Unauthorized');
        }

        $history = DB::table('plugin_blindbox_history')
            ->join('users', 'plugin_blindbox_history.user_id', '=', 'users.id')
            ->select('plugin_blindbox_history.*', 'users.username')
            ->orderBy('plugin_blindbox_history.created_at', 'desc')
            ->paginate(50);

        return view('blindbox::admin.history', compact('history'));
    }
}
