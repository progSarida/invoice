<?php

namespace App\Filament\Company\Resources\PassiveDownloadResource\Pages;

use App\Filament\Company\Resources\PassiveDownloadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPassiveDownloads extends ListRecords
{
    protected static string $resource = PassiveDownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
