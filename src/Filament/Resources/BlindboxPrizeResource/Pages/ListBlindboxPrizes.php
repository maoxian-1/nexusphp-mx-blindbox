<?php

namespace NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource\Pages;

use NexusPlugin\Blindbox\Filament\Resources\BlindboxPrizeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlindboxPrizes extends ListRecords
{
    protected static string $resource = BlindboxPrizeResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
