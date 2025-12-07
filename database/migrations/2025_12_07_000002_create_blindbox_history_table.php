<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlindboxHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plugin_blindbox_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('prize_id')->comment('奖品ID');
            $table->string('prize_name')->comment('奖品名称（冗余）');
            $table->string('prize_type')->comment('奖品类型（冗余）');
            $table->decimal('prize_value', 20, 2)->comment('奖品数值（冗余）');
            $table->boolean('is_free')->default(false)->comment('是否免费抽取');
            $table->decimal('cost', 10, 2)->default(0)->comment('消耗魔力值');
            $table->string('ip')->nullable()->comment('IP地址');
            $table->timestamps();

            $table->index('user_id');
            $table->index('prize_id');
            $table->index('is_free');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plugin_blindbox_history');
    }
}
