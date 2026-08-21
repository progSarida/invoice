<?php

namespace App\Filament\Company\Resources\PassivePaymentResource\Pages;

use App\Filament\Company\Resources\PassivePaymentResource;
use App\Filament\Exports\PassivePaymentExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;

class ListPassivePayments extends ListRecords
{
    protected static string $resource = PassivePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco pagamenti passivi')
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->action(function ($livewire) {
                    // Record della query filtrata della tabella: si stampa quello che si sta vedendo
                    $records = $livewire->getFilteredTableQuery()
                        ->with('passiveInvoice.supplier')                       // evita una query per riga su fattura e fornitore
                        ->get();

                    $filters = $livewire->tableFilters ?? [];                   // filtri applicati alla tabella
                    $search = $livewire->tableSearch ?? null;                   // stringa di ricerca

                    $fileName = 'Pagamenti_passivi_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';

                    return response()->streamDownload(function () use ($records, $search, $filters) {
                        $pdf = Pdf::loadHTML(
                            Blade::render('pdf.passive_payments', [
                                'payments' => $records,
                                'search' => $search,
                                'filters' => $filters,
                            ])
                        )
                        ->setPaper('A4', 'landscape')
                        ->setOptions([
                            'isHtml5ParserEnabled' => true,
                            'isPhpEnabled' => true,
                            'isFontSubsettingEnabled' => true,
                        ]);

                        echo $pdf->stream();
                    }, $fileName);
                }),
            ExportAction::make('esporta')
                ->icon('heroicon-s-table-cells')
                ->label('Esporta')
                ->tooltip('Esporta elenco pagamenti passivi')
                ->color(Color::rgb('rgb(0,153,0)'))
                ->exporter(PassivePaymentExporter::class)
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
