<?php

namespace App\Filament\Resources\LimitMotivationTypeResource\Pages;

use App\Filament\Resources\LimitMotivationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListLimitMotivationTypes extends ListRecords
{
    protected static string $resource = LimitMotivationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
