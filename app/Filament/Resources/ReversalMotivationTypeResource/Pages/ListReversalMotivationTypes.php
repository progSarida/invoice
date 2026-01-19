<?php

namespace App\Filament\Resources\ReversalMotivationTypeResource\Pages;

use App\Filament\Resources\ReversalMotivationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReversalMotivationTypes extends ListRecords
{
    protected static string $resource = ReversalMotivationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
