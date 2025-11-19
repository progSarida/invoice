<?php

namespace App\Filament\Resources\ManageTypeResource\Pages;

use App\Filament\Resources\ManageTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListManageTypes extends ListRecords
{
    protected static string $resource = ManageTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
