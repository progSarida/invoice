<?php

namespace App\Filament\Company\Resources\SdiRequestResource\Pages;

use App\Filament\Company\Resources\SdiRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSdiRequests extends ListRecords
{
    protected static string $resource = SdiRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
}
