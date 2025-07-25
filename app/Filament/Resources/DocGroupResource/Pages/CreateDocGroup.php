<?php

namespace App\Filament\Resources\DocGroupResource\Pages;

use App\Filament\Resources\DocGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDocGroup extends CreateRecord
{
    protected static string $resource = DocGroupResource::class;

    public function getTitle(): string
    {
        return "Nuovo gruppo documenti";
    }
}
