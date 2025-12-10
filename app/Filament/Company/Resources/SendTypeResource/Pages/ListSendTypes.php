<?php

namespace App\Filament\Company\Resources\SendTypeResource\Pages;

use App\Filament\Company\Resources\SendTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSendTypes extends ListRecords
{
    protected static string $resource = SendTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
