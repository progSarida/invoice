<?php

namespace App\Filament\Company\Resources\PassiveDownloadResource\Pages;

use App\Filament\Company\Resources\PassiveDownloadResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPassiveDownload extends ViewRecord
{
    protected static string $resource = PassiveDownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
