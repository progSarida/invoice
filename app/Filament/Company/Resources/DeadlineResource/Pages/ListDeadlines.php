<?php

namespace App\Filament\Company\Resources\DeadlineResource\Pages;

use App\Filament\Company\Resources\DeadlineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeadlines extends ListRecords
{
    protected static string $resource = DeadlineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
