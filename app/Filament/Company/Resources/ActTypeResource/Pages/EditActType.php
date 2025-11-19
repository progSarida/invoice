<?php

namespace App\Filament\Company\Resources\ActTypeResource\Pages;

use App\Filament\Company\Resources\ActTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditActType extends EditRecord
{
    protected static string $resource = ActTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
