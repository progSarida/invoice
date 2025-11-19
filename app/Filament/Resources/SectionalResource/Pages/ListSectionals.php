<?php

namespace App\Filament\Resources\SectionalResource\Pages;

use App\Filament\Resources\SectionalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSectionals extends ListRecords
{
    protected static string $resource = SectionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
