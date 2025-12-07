<?php

namespace NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource\Pages;

use NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlindboxPrize extends EditRecord
{
    protected static string $resource = BlindboxPrizeResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
