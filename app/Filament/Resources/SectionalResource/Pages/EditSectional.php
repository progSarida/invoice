<?php

namespace App\Filament\Resources\SectionalResource\Pages;

use App\Filament\Resources\SectionalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSectional extends EditRecord
{
    protected static string $resource = SectionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
