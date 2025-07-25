<?php

namespace App\Filament\Resources\ManageTypeResource\Pages;

use App\Filament\Resources\ManageTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateManageType extends CreateRecord
{
    protected static string $resource = ManageTypeResource::class;

    public function getTitle(): string
    {
        return "Nuova gestione";
    }
}
