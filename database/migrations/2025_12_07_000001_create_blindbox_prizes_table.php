<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlindboxPrizesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plugin_blindbox_prizes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('奖品名称');
            $table->text('description')->nullable()->comment('奖品描述');
            $table->enum('type', ['bonus', 'upload', 'vip_days', 'invite', 'medal', 'rainbow_id'])
                ->comment('奖品类型：魔力值、上传量、VIP天数、邀请名额、勋章、彩虹ID');
            $table->decimal('value', 20, 2)->default(0)->comment('奖品数值');
            $table->string('medal_id')->nullable()->comment('勋章ID(如果是勋章类型)');
            $table->decimal('medal_bonus', 10, 2)->default(100)->comment('已有勋章转换魔力值（当type为medal时）');
            $table->integer('rainbow_days')->default(0)->comment('彩虹ID天数（当type为rainbow_id时）');
            $table->decimal('probability', 5, 2)->comment('中奖概率(%)');
            $table->integer('daily_limit')->default(0)->comment('每日限量，0为不限');
            $table->integer('total_limit')->default(0)->comment('总限量，0为不限');
            $table->integer('given_count')->default(0)->comment('已发放数量');
            $table->integer('given_today')->default(0)->comment('今日已发放数量');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plugin_blindbox_prizes');
    }
}
