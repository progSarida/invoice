<?php

namespace App\Filament\Resources\AccrualTypeResource\Pages;

use App\Filament\Resources\AccrualTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccrualTypes extends ListRecords
{
    protected static string $resource = AccrualTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
