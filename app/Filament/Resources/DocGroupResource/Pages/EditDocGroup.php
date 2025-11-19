<?php

namespace App\Filament\Resources\DocGroupResource\Pages;

use App\Filament\Resources\DocGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocGroup extends EditRecord
{
    protected static string $resource = DocGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
