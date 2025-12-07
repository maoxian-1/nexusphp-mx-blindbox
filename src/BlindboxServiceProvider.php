<?php
namespace NexusPlugin\Blindbox;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use NexusPlugin\Blindbox\Services\BlindboxService;
use NexusPlugin\Blindbox\Console\Commands\InstallBlindboxCommand;
use NexusPlugin\Blindbox\Console\Commands\ResetDailyBlindboxCommand;

class BlindboxServiceProvider extends ServiceProvider
{
    public function register(): void 
    {
        // 注册服务
        $this->app->singleton(BlindboxService::class, function ($app) {
            return new BlindboxService();
        });
        
        // 合并配置
        $this->mergeConfigFrom(
            __DIR__ . '/../config/blindbox.php', 'blindbox'
        );
    }

    public function boot(): void 
    {
        // 加载路由
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        
        // 加载数据库迁移
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // 加载视图
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'blindbox');
        
        // 加载语言包
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'blindbox');
        
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/blindbox.php' => config_path('blindbox.php'),
        ], 'blindbox-config');
        
        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallBlindboxCommand::class,
                ResetDailyBlindboxCommand::class,
            ]);
        }
        
        // 注册Filament资源 - 暂时禁用，需要适配Filament版本
        // if (class_exists('Filament\Facades\Filament')) {
        //     \Filament\Facades\Filament::serving(function () {
        //         \Filament\Facades\Filament::registerResources([
        //             \NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource::class,
        //         ]);
        //     });
        // }
    }
}
