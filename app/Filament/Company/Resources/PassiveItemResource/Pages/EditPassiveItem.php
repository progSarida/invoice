<?php

namespace App\Filament\Company\Resources\PassiveItemResource\Pages;

use App\Filament\Company\Resources\PassiveItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPassiveItem extends EditRecord
{
    protected static string $resource = PassiveItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
