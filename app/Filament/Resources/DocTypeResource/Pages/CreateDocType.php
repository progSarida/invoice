<?php

namespace App\Filament\Resources\DocTypeResource\Pages;

use App\Filament\Resources\DocTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDocType extends CreateRecord
{
    protected static string $resource = DocTypeResource::class;

    public function getTitle(): string
    {
        return "Nuovo tipo documento";
    }
}
