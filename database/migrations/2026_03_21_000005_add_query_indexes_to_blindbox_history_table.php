<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQueryIndexesToBlindboxHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plugin_blindbox_history', function (Blueprint $table) {
            // 覆盖用户当日统计、免费次数判断、用户历史列表查询
            $table->index(['user_id', 'created_at', 'is_free'], 'blindbox_history_user_created_free_idx');

            // 覆盖按当日时间范围聚合每个奖品发放次数的查询
            $table->index(['created_at', 'prize_id'], 'blindbox_history_created_prize_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plugin_blindbox_history', function (Blueprint $table) {
            $table->dropIndex('blindbox_history_user_created_free_idx');
            $table->dropIndex('blindbox_history_created_prize_idx');
        });
    }
}
