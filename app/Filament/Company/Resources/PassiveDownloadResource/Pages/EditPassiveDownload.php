<?php

namespace App\Filament\Company\Resources\PassiveDownloadResource\Pages;

use App\Filament\Company\Resources\PassiveDownloadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPassiveDownload extends EditRecord
{
    protected static string $resource = PassiveDownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
