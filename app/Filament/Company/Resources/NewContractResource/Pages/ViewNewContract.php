<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use App\Filament\Company\Resources\NewContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNewContract extends ViewRecord
{
    protected static string $resource = NewContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
