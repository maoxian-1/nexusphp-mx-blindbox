# NexusPHP 盲盒插件

一个功能完整的NexusPHP盲盒抽奖插件，支持多种奖品类型和灵活的配置。

## 功能特性

- 🎁 支持多种奖品类型：魔力值、上传量、VIP天数、邀请名额、勋章、彩虹ID
- 🎯 灵活的中奖概率配置
- 🆓 每日免费抽奖机会
- 📊 完整的后台管理界面（基于Filament）
- 🌐 多语言支持（中文、英文）
- 📱 精美的前端悬浮组件
- 📈 抽奖历史记录
- 🔒 安全的抽奖机制

## 安装步骤

### 1. 下载插件

将插件文件夹 `nexusphp-blindbox` 放置到 `plugins/` 目录下。

### 2. 更新Composer自动加载

```bash
composer dump-autoload
```

### 3. 运行安装命令

```bash
# 基础安装
php artisan blindbox:install

# 安装并添加默认奖品
php artisan blindbox:install --seed
```

### 4. 清除缓存

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. 设置定时任务（可选）

如果需要每日自动重置统计数据，在 `app/Console/Kernel.php` 中添加：

```php
protected function schedule(Schedule $schedule)
{
    // 每日凌晨重置盲盒统计
    $schedule->command('blindbox:reset-daily')->dailyAt('00:00');
}
```

## 配置说明

### 基础配置

配置文件位于 `config/blindbox.php`：

```php
return [
    'enabled' => true,              // 是否启用盲盒功能
    'draw_cost' => 100,            // 每次抽奖消耗的魔力值
    'daily_free' => true,          // 是否启用每日免费抽奖
    'daily_free_times' => 1,       // 每日免费抽奖次数
];
```

### 奖品配置

通过后台管理界面配置奖品：

1. 访问管理后台 `/admin`
2. 找到"插件管理" -> "盲盒奖品管理"
3. 添加或编辑奖品

每个奖品可配置：
- 奖品名称和描述
- 奖品类型和数值
- 中奖概率（所有奖品概率总和应为100%）
- 每日限量和总限量
- 排序顺序

## 使用说明

### 用户端

1. 用户在首页会看到悬浮的盲盒图标
2. 点击图标打开抽奖界面
3. 可以使用免费机会或消耗魔力值抽奖
4. 查看抽奖历史记录

### 管理端

1. 通过Filament后台管理奖品
2. 查看抽奖统计数据
3. 管理用户抽奖记录

## API接口

插件提供以下API接口：

- `POST /api/blindbox/draw` - 执行抽奖
- `GET /api/blindbox/history` - 获取抽奖历史
- `GET /api/blindbox/prizes` - 获取奖品列表

## 数据库表

插件会创建以下数据库表：

- `plugin_blindbox_prizes` - 奖品配置表
- `plugin_blindbox_history` - 抽奖历史记录表

## 卸载

如需卸载插件：

1. 删除数据库表：
```bash
php artisan migrate:rollback --path=plugins/nexusphp-blindbox/database/migrations
```

2. 删除插件文件夹
3. 清除缓存

## 开发说明

### 目录结构

```
nexusphp-blindbox/
├── composer.json              # Composer配置
├── config/
│   └── blindbox.php          # 配置文件
├── database/
│   └── migrations/           # 数据库迁移
├── resources/
│   └── lang/                 # 语言文件
├── routes/
│   └── api.php              # API路由
└── src/
    ├── Console/             # 命令行工具
    ├── Filament/           # 后台管理资源
    ├── Http/               # 控制器
    ├── Models/             # 数据模型
    ├── Services/           # 业务逻辑
    ├── Blindbox.php        # 主类
    ├── BlindboxRepository.php  # 仓库类
    └── BlindboxServiceProvider.php  # 服务提供者
```

### 扩展开发

如需添加新的奖品类型，修改 `BlindboxService::givePrize()` 方法并添加相应的发放逻辑。

## 许可证

MIT License

## 支持

如有问题或建议，请提交Issue。
