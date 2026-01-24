<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddValueRangeToBlindboxPrizesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plugin_blindbox_prizes', function (Blueprint $table) {
            // 添加最小值和最大值字段，用于随机奖励
            $table->bigInteger('value_min')->nullable()->after('value')->comment('奖品最小数值（用于随机，整数）');
            $table->bigInteger('value_max')->nullable()->after('value_min')->comment('奖品最大数值（用于随机，整数）');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plugin_blindbox_prizes', function (Blueprint $table) {
            $table->dropColumn(['value_min', 'value_max']);
        });
    }
}
