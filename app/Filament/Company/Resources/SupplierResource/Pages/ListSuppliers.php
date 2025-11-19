<?php

namespace App\Filament\Company\Resources\SupplierResource\Pages;

use App\Filament\Company\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('ledger')
                ->icon('tabler-report-search')
                ->label('Partitario')
                ->tooltip('Stampa partitario fornitori')
                ->color('primary')
                ->modalWidth('6xl')
                ->modalHeading('Partitario')
                ->form([
                    \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            //
                        ]),
                ])
                ->action(function ($data) {
                    //
                }),
        ];
    }
}
