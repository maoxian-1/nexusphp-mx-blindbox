<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddAdminpanelMenu extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 向adminpanel表插入盲盒管理菜单项
        DB::table('adminpanel')->insert([
            'name' => '盲盒管理',
            'url' => 'plugins/nexusphp-blindbox/admin.php',
            'info' => '管理盲盒奖品和设置'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 删除盲盒管理菜单项
        DB::table('adminpanel')
            ->where('url', 'plugins/nexusphp-blindbox/admin.php')
            ->delete();
    }
}