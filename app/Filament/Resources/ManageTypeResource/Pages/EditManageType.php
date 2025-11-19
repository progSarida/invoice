<?php

namespace App\Filament\Resources\ManageTypeResource\Pages;

use App\Filament\Resources\ManageTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManageType extends EditRecord
{
    protected static string $resource = ManageTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
