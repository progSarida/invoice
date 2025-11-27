<?php

namespace App\Filament\Company\Resources\PassivePaymentResource\Pages;

use App\Filament\Company\Resources\PassivePaymentResource;
use App\Filament\Exports\PassivePaymentExporter;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;

class ListPassivePayments extends ListRecords
{
    protected static string $resource = PassivePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make('esporta')
                ->icon('heroicon-s-table-cells')
                ->label('Esporta')
                ->tooltip('Esporta elenco pagamenti passivi')
                ->color(Color::rgb('rgb(0,153,0)'))
                ->exporter(PassivePaymentExporter::class)
        ];
    }
}
