<?php

namespace App\Filament\Company\Resources\BailResource\Pages;

use App\Filament\Company\Resources\BailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBail extends EditRecord
{
    protected static string $resource = BailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
