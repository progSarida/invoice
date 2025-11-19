<?php

namespace App\Filament\Company\Resources\DeadlineResource\Pages;

use App\Filament\Company\Resources\DeadlineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeadline extends EditRecord
{
    protected static string $resource = DeadlineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
