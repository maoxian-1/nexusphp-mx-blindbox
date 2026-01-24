<?php

namespace NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource\Pages;

use NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource;
use NexusPlugin\Blindbox\Models\BlindboxPrize;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use App\Models\Setting;
use Filament\Notifications\Notification;

class ListBlindboxPrizes extends ListRecords
{
    protected static string $resource = BlindboxPrizeResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            BlindboxStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新增奖品')
                ->modalHeading('新增奖品')
                ->modalWidth('4xl'),
                
            Actions\Action::make('settings')
                ->label('全局设置')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->form([
                    Section::make('基础设置')
                        ->schema([
                            Toggle::make('enabled')
                                ->label('启用盲盒功能')
                                ->default(fn () => Setting::get('plugin.blindbox.enabled', 'yes') === 'yes'),
                                
                            Toggle::make('daily_free')
                                ->label('启用每日免费抽奖')
                                ->default(fn () => Setting::get('plugin.blindbox.daily_free', 'yes') === 'yes'),
                                
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('draw_cost')
                                        ->label('每次抽奖消耗魔力值')
                                        ->numeric()
                                        ->default(fn () => Setting::get('plugin.blindbox.draw_cost', '100')),
                                        
                                    TextInput::make('daily_free_times')
                                        ->label('每日免费次数')
                                        ->numeric()
                                        ->default(fn () => Setting::get('plugin.blindbox.daily_free_times', '1')),
                                ]),
                                
                            Toggle::make('show_on_torrent')
                                ->label('在种子列表页显示盲盒')
                                ->default(fn () => Setting::get('plugin.blindbox.show_on_torrent', 'no') === 'yes'),
                        ]),
                ])
                ->action(function (array $data) {
                    Setting::set('plugin.blindbox.enabled', $data['enabled'] ? 'yes' : 'no');
                    Setting::set('plugin.blindbox.daily_free', $data['daily_free'] ? 'yes' : 'no');
                    Setting::set('plugin.blindbox.draw_cost', $data['draw_cost']);
                    Setting::set('plugin.blindbox.daily_free_times', $data['daily_free_times']);
                    Setting::set('plugin.blindbox.show_on_torrent', $data['show_on_torrent'] ? 'yes' : 'no');
                    
                    Notification::make()
                        ->title('设置已保存')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('reset_daily')
                ->label('重置今日统计')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('重置今日统计')
                ->modalDescription('确定要重置所有奖品的今日发放数量吗？此操作不可撤销。')
                ->action(function () {
                    BlindboxPrize::query()->update(['given_today' => 0]);
                    Notification::make()
                        ->title('今日统计已重置')
                        ->success()
                        ->send();
                }),
        ];
    }
}

// 统计 Widget
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class BlindboxStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalDraws = DB::table('plugin_blindbox_history')->count();
        $todayDraws = DB::table('plugin_blindbox_history')
            ->whereDate('created_at', today())
            ->count();
        $totalUsers = DB::table('plugin_blindbox_history')
            ->distinct('user_id')
            ->count('user_id');
        $activePrizes = BlindboxPrize::where('is_active', true)->count();
        $totalProbability = BlindboxPrize::where('is_active', true)->sum('probability');
        
        return [
            Stat::make('总抽奖次数', number_format($totalDraws))
                ->description('累计抽奖')
                ->icon('heroicon-o-gift')
                ->color('primary'),
                
            Stat::make('今日抽奖', number_format($todayDraws))
                ->description('今日参与次数')
                ->icon('heroicon-o-calendar')
                ->color('success'),
                
            Stat::make('参与用户', number_format($totalUsers))
                ->description('累计参与人数')
                ->icon('heroicon-o-users')
                ->color('info'),
                
            Stat::make('活跃奖品', $activePrizes)
                ->description("概率总和: {$totalProbability}%")
                ->icon('heroicon-o-sparkles')
                ->color($totalProbability == 100 ? 'success' : 'warning'),
        ];
    }
}
