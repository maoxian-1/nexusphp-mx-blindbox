<?php

namespace NexusPlugin\Blindbox\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use NexusPlugin\Blindbox\Models\BlindboxPrize;

class BlindboxPrizeResource extends Resource
{
    protected static ?string $model = BlindboxPrize::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    
    protected static ?string $navigationLabel = '盲盒奖品管理';
    
    protected static ?string $navigationGroup = '插件管理';
    
    protected static ?string $modelLabel = '盲盒奖品';
    
    protected static ?string $pluralModelLabel = '盲盒奖品';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('奖品配置')
                    ->tabs([
                        Tabs\Tab::make('基本信息')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('奖品名称')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('如：随机魔力值'),
                                            
                                        Forms\Components\Select::make('type')
                                            ->label('奖品类型')
                                            ->options([
                                                'bonus' => '🎁 魔力值',
                                                'upload' => '📤 上传量',
                                                'vip_days' => '👑 VIP天数',
                                                'invite' => '💌 邀请名额',
                                                'medal' => '🏅 勋章',
                                                'rainbow_id' => '🌈 彩虹ID',
                                            ])
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('value', 0)),
                                    ]),
                                    
                                Forms\Components\Textarea::make('description')
                                    ->label('奖品描述')
                                    ->maxLength(65535)
                                    ->placeholder('奖品的详细描述，会显示给用户')
                                    ->columnSpanFull(),
                            ]),
                            
                        Tabs\Tab::make('奖励数值')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Section::make('固定数值模式')
                                    ->description('设置固定的奖励数值')
                                    ->schema([
                                        Forms\Components\TextInput::make('value')
                                            ->label('奖品数值')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->helperText(function (Get $get) {
                                                $type = $get('type');
                                                return match($type) {
                                                    'bonus' => '魔力值数量（整数）',
                                                    'upload' => '上传量（字节），1GB = 1073741824',
                                                    'vip_days' => 'VIP天数',
                                                    'invite' => '邀请名额数量',
                                                    'medal' => '可留空（使用勋章ID）',
                                                    'rainbow_id' => '彩虹ID天数',
                                                    default => '数值'
                                                };
                                            }),
                                    ])
                                    ->collapsible(),
                                    
                                Section::make('随机范围模式（仅魔力值和上传量）')
                                    ->description('设置最小值和最大值后，每次抽奖将在范围内随机')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('value_min')
                                                    ->label('最小值')
                                                    ->numeric()
                                                    ->nullable()
                                                    ->helperText(function (Get $get) {
                                                        return $get('type') === 'upload' ? '字节数' : '整数';
                                                    }),
                                                    
                                                Forms\Components\TextInput::make('value_max')
                                                    ->label('最大值')
                                                    ->numeric()
                                                    ->nullable()
                                                    ->helperText(function (Get $get) {
                                                        return $get('type') === 'upload' ? '字节数' : '整数';
                                                    }),
                                            ]),
                                            
                                        Forms\Components\Placeholder::make('range_note')
                                            ->label('')
                                            ->content('💡 提示：仅对"魔力值"和"上传量"类型生效。同时设置最小值和最大值后，固定数值将被忽略。')
                                            ->visible(fn (Get $get) => in_array($get('type'), ['bonus', 'upload'])),
                                    ])
                                    ->collapsible()
                                    ->collapsed()
                                    ->visible(fn (Get $get) => in_array($get('type'), ['bonus', 'upload'])),
                                    
                                Section::make('勋章专属设置')
                                    ->schema([
                                        Forms\Components\TextInput::make('medal_id')
                                            ->label('勋章ID')
                                            ->helperText('系统中的勋章ID'),
                                            
                                        Forms\Components\TextInput::make('medal_bonus')
                                            ->label('重复勋章转换魔力值')
                                            ->numeric()
                                            ->default(100)
                                            ->helperText('用户已有该勋章时，转换为魔力值的数量'),
                                    ])
                                    ->visible(fn (Get $get) => $get('type') === 'medal')
                                    ->columns(2),
                                    
                                Section::make('彩虹ID专属设置')
                                    ->schema([
                                        Forms\Components\TextInput::make('rainbow_days')
                                            ->label('彩虹ID天数')
                                            ->numeric()
                                            ->default(7)
                                            ->helperText('彩虹ID特权持续天数'),
                                    ])
                                    ->visible(fn (Get $get) => $get('type') === 'rainbow_id'),
                            ]),
                            
                        Tabs\Tab::make('概率与限制')
                            ->icon('heroicon-o-chart-pie')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('probability')
                                            ->label('中奖概率(%)')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0.01)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->suffix('%')
                                            ->helperText('所有奖品概率总和建议为100%'),
                                            
                                        Forms\Components\TextInput::make('daily_limit')
                                            ->label('每日限量')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('0 = 不限'),
                                            
                                        Forms\Components\TextInput::make('total_limit')
                                            ->label('总限量')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('0 = 不限'),
                                    ]),
                                    
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('排序权重')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('数字越小越靠前'),
                                            
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('启用状态')
                                            ->default(true)
                                            ->helperText('关闭后该奖品不会出现在奖池中'),
                                    ]),
                            ]),
                            
                        Tabs\Tab::make('统计信息')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\Placeholder::make('given_count_display')
                                            ->label('累计发放')
                                            ->content(fn ($record) => $record?->given_count ?? 0),
                                            
                                        Forms\Components\Placeholder::make('given_today_display')
                                            ->label('今日发放')
                                            ->content(fn ($record) => $record?->today_given_count ?? 0),
                                            
                                        Forms\Components\Placeholder::make('created_at_display')
                                            ->label('创建时间')
                                            ->content(fn ($record) => $record?->created_at?->format('Y-m-d H:i:s') ?? '-'),
                                            
                                        Forms\Components\Placeholder::make('updated_at_display')
                                            ->label('更新时间')
                                            ->content(fn ($record) => $record?->updated_at?->format('Y-m-d H:i:s') ?? '-'),
                                    ]),
                            ])
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(50),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('奖品名称')
                    ->searchable()
                    ->weight('bold'),
                    
                Tables\Columns\BadgeColumn::make('type')
                    ->label('类型')
                    ->colors([
                        'primary' => 'bonus',
                        'success' => 'upload',
                        'warning' => 'vip_days',
                        'danger' => 'invite',
                        'gray' => 'medal',
                        'info' => 'rainbow_id',
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'bonus' => '🎁 魔力值',
                        'upload' => '📤 上传量',
                        'vip_days' => '👑 VIP天数',
                        'invite' => '💌 邀请',
                        'medal' => '🏅 勋章',
                        'rainbow_id' => '🌈 彩虹ID',
                        default => $state
                    }),
                    
                Tables\Columns\TextColumn::make('value_display')
                    ->label('奖励值')
                    ->getStateUsing(function ($record) {
                        // 显示随机范围或固定值
                        if ($record->value_min !== null && $record->value_max !== null && in_array($record->type, ['bonus', 'upload'])) {
                            if ($record->type === 'upload') {
                                $minGB = number_format($record->value_min / 1073741824, 2);
                                $maxGB = number_format($record->value_max / 1073741824, 2);
                                return "🎲 {$minGB} - {$maxGB} GB";
                            }
                            return "🎲 {$record->value_min} - {$record->value_max}";
                        }
                        
                        return match($record->type) {
                            'upload' => number_format($record->value / 1073741824, 2) . ' GB',
                            'vip_days' => $record->value . ' 天',
                            'invite' => $record->value . ' 个',
                            'rainbow_id' => ($record->rainbow_days ?: $record->value) . ' 天',
                            default => $record->value
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('probability')
                    ->label('概率')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('given_count')
                    ->label('已发放')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('today_given_count')
                    ->label('今日')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('today_given_count', $direction)),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('状态')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        'bonus' => '魔力值',
                        'upload' => '上传量',
                        'vip_days' => 'VIP天数',
                        'invite' => '邀请名额',
                        'medal' => '勋章',
                        'rainbow_id' => '彩虹ID',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('启用状态'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('编辑')
                    ->modalHeading('编辑奖品')
                    ->modalWidth('4xl'),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? '停用' : '启用')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('删除'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('批量删除'),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('批量启用')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('批量停用')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->poll('30s');
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $todayPrizeStats = DB::table('plugin_blindbox_history')
            ->select('prize_id', DB::raw('COUNT(*) as today_given_count'))
            ->whereRaw('created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)')
            ->groupBy('prize_id');

        return parent::getEloquentQuery()
            ->leftJoinSub($todayPrizeStats, 'today_stats', function ($join) {
                $join->on('today_stats.prize_id', '=', 'plugin_blindbox_prizes.id');
            })
            ->select('plugin_blindbox_prizes.*', DB::raw('COALESCE(today_stats.today_given_count, 0) as today_given_count'));
    }
    
    public static function getPages(): array
    {
        return [
            'index' => \NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource\Pages\ListBlindboxPrizes::route('/'),
        ];
    }
}
