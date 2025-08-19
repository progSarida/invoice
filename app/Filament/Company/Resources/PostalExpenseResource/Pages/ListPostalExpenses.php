<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Pages;

use App\Filament\Company\Resources\PostalExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListPostalExpenses extends ListRecords
{
    protected static string $resource = PostalExpenseResource::class;

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
