<?php

namespace NexusPlugin\Blindbox\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use NexusPlugin\Blindbox\Models\BlindboxPrize;

class BlindboxPrizeResource extends Resource
{
    protected static ?string $model = BlindboxPrize::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    
    protected static ?string $navigationLabel = '盲盒奖品管理';
    
    protected static ?string $navigationGroup = '插件管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('奖品名称')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\Textarea::make('description')
                    ->label('奖品描述')
                    ->maxLength(65535),
                    
                Forms\Components\Select::make('type')
                    ->label('奖品类型')
                    ->options([
                        'bonus' => '魔力值',
                        'upload' => '上传量',
                        'vip_days' => 'VIP天数',
                        'invite' => '邀请名额',
                        'medal' => '勋章',
                        'rainbow_id' => '彩虹ID',
                    ])
                    ->required()
                    ->reactive(),
                    
                Forms\Components\TextInput::make('value')
                    ->label('奖品数值')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->helperText(function ($get) {
                        $type = $get('type');
                        return match($type) {
                            'bonus' => '魔力值数量',
                            'upload' => '上传量（字节）',
                            'vip_days' => 'VIP天数',
                            'invite' => '邀请名额数量',
                            'medal' => '勋章ID',
                            'rainbow_id' => '彩虹ID天数',
                            default => '数值'
                        };
                    }),
                    
                Forms\Components\TextInput::make('medal_id')
                    ->label('勋章ID')
                    ->visible(fn ($get) => $get('type') === 'medal'),
                    
                Forms\Components\TextInput::make('rainbow_days')
                    ->label('彩虹ID天数')
                    ->numeric()
                    ->visible(fn ($get) => $get('type') === 'rainbow_id')
                    ->default(0),
                    
                Forms\Components\TextInput::make('probability')
                    ->label('中奖概率(%)')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(100)
                    ->step(0.01)
                    ->helperText('所有奖品概率总和应为100%'),
                    
                Forms\Components\TextInput::make('daily_limit')
                    ->label('每日限量')
                    ->numeric()
                    ->default(0)
                    ->helperText('0表示不限'),
                    
                Forms\Components\TextInput::make('total_limit')
                    ->label('总限量')
                    ->numeric()
                    ->default(0)
                    ->helperText('0表示不限'),
                    
                Forms\Components\TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0),
                    
                Forms\Components\Toggle::make('is_active')
                    ->label('是否启用')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('奖品名称')
                    ->searchable(),
                    
                Tables\Columns\BadgeColumn::make('type')
                    ->label('类型')
                    ->colors([
                        'primary' => 'bonus',
                        'success' => 'upload',
                        'warning' => 'vip_days',
                        'danger' => 'invite',
                        'secondary' => 'medal',
                        'info' => 'rainbow_id',
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'bonus' => '魔力值',
                        'upload' => '上传量',
                        'vip_days' => 'VIP天数',
                        'invite' => '邀请名额',
                        'medal' => '勋章',
                        'rainbow_id' => '彩虹ID',
                        default => $state
                    }),
                    
                Tables\Columns\TextColumn::make('value')
                    ->label('数值')
                    ->formatStateUsing(function ($state, $record) {
                        return match($record->type) {
                            'upload' => number_format($state / 1073741824, 2) . ' GB',
                            'vip_days' => $state . ' 天',
                            'invite' => $state . ' 个',
                            default => $state
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('probability')
                    ->label('概率')
                    ->formatStateUsing(fn ($state) => $state . '%'),
                    
                Tables\Columns\TextColumn::make('given_count')
                    ->label('已发放'),
                    
                Tables\Columns\TextColumn::make('given_today')
                    ->label('今日发放'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('状态')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('sort_order', 'asc');
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => \NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource\Pages\ListBlindboxPrizes::route('/'),
            'create' => \NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource\Pages\CreateBlindboxPrize::route('/create'),
            'edit' => \NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource\Pages\EditBlindboxPrize::route('/{record}/edit'),
        ];
    }
}
