<?php

namespace App\Filament\Company\Resources\ContainerResource\Pages;

use App\Filament\Company\Resources\ContainerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContainer extends EditRecord
{
    protected static string $resource = ContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }


    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
