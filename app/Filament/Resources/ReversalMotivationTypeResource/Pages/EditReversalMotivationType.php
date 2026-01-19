<?php

namespace App\Filament\Resources\ReversalMotivationTypeResource\Pages;

use App\Filament\Resources\ReversalMotivationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReversalMotivationType extends EditRecord
{
    protected static string $resource = ReversalMotivationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
