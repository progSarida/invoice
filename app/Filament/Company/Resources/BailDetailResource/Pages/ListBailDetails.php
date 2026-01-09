<?php

namespace App\Filament\Company\Resources\BailDetailResource\Pages;

use App\Filament\Company\Resources\BailDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBailDetails extends ListRecords
{
    protected static string $resource = BailDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
