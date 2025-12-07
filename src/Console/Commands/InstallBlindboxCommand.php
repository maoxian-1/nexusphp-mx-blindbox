<?php

namespace NexusPlugin\Blindbox\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Setting;

class InstallBlindboxCommand extends Command
{
    protected $signature = 'blindbox:install {--seed : Seed default prizes}';
    
    protected $description = 'Install Blindbox plugin';

    public function handle()
    {
        $this->info('Installing Blindbox plugin...');
        
        // 运行数据库迁移
        $this->info('Running migrations...');
        Artisan::call('migrate', [
            '--path' => 'plugins/nexusphp-blindbox/database/migrations',
            '--force' => true,
        ]);
        
        // 初始化设置
        $this->info('Initializing settings...');
        $this->initializeSettings();
        
        // 如果指定了 --seed 参数，则添加默认奖品
        if ($this->option('seed')) {
            $this->info('Seeding default prizes...');
            $this->seedDefaultPrizes();
        }
        
        $this->info('Blindbox plugin installed successfully!');
        $this->info('Please run "php artisan config:cache" to refresh configuration.');
    }
    
    private function initializeSettings()
    {
        // 设置默认配置
        $settings = [
            ['name' => 'plugin.blindbox.enabled', 'value' => 'yes', 'autoload' => 'yes'],
            ['name' => 'plugin.blindbox.draw_cost', 'value' => '100', 'autoload' => 'yes'],
            ['name' => 'plugin.blindbox.daily_free', 'value' => 'yes', 'autoload' => 'yes'],
            ['name' => 'plugin.blindbox.daily_free_times', 'value' => '1', 'autoload' => 'yes'],
        ];
        
        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['name' => $setting['name']],
                ['value' => $setting['value'], 'autoload' => $setting['autoload']]
            );
        }
        
        // 清除设置缓存
        \Nexus\Database\NexusDB::cache_del('nexus_settings_in_laravel');
        
        $this->info('Settings initialized.');
    }
    
    private function seedDefaultPrizes()
    {
        $prizes = config('blindbox.default_prizes', []);
        
        if (empty($prizes)) {
            $prizes = [
                [
                    'name' => '少量魔力值',
                    'type' => 'bonus',
                    'value' => 50,
                    'probability' => 30,
                    'description' => '获得50魔力值'
                ],
                [
                    'name' => '中量魔力值',
                    'type' => 'bonus',
                    'value' => 100,
                    'probability' => 20,
                    'description' => '获得100魔力值'
                ],
                [
                    'name' => '大量魔力值',
                    'type' => 'bonus',
                    'value' => 500,
                    'probability' => 5,
                    'description' => '获得500魔力值'
                ],
                [
                    'name' => '1GB上传量',
                    'type' => 'upload',
                    'value' => 1073741824,
                    'probability' => 15,
                    'description' => '获得1GB上传量'
                ],
                [
                    'name' => '5GB上传量',
                    'type' => 'upload',
                    'value' => 5368709120,
                    'probability' => 10,
                    'description' => '获得5GB上传量'
                ],
                [
                    'name' => 'VIP 1天',
                    'type' => 'vip_days',
                    'value' => 1,
                    'probability' => 10,
                    'description' => '获得1天VIP会员'
                ],
                [
                    'name' => 'VIP 7天',
                    'type' => 'vip_days',
                    'value' => 7,
                    'probability' => 5,
                    'description' => '获得7天VIP会员'
                ],
                [
                    'name' => '临时邀请名额',
                    'type' => 'invite',
                    'value' => 1,
                    'probability' => 3,
                    'description' => '获得1个临时邀请名额'
                ],
                [
                    'name' => '彩虹ID 7天',
                    'type' => 'rainbow_id',
                    'value' => 7,
                    'rainbow_days' => 7,
                    'probability' => 2,
                    'description' => '获得7天彩虹ID特权'
                ]
            ];
        }
        
        foreach ($prizes as $index => $prize) {
            DB::table('plugin_blindbox_prizes')->insert([
                'name' => $prize['name'],
                'description' => $prize['description'] ?? '',
                'type' => $prize['type'],
                'value' => $prize['value'],
                'medal_id' => $prize['medal_id'] ?? null,
                'rainbow_days' => $prize['rainbow_days'] ?? 0,
                'probability' => $prize['probability'],
                'daily_limit' => $prize['daily_limit'] ?? 0,
                'total_limit' => $prize['total_limit'] ?? 0,
                'given_count' => 0,
                'given_today' => 0,
                'is_active' => true,
                'sort_order' => $index * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->info('Default prizes seeded.');
    }
}
