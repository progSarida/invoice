<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Filament\Exports\PassiveInvoiceExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;

class ListPassiveInvoices extends ListRecords
{
    protected static string $resource = PassiveInvoiceResource::class;

    /**
     * Il numero di righe per pagina non viene mantenuto in sessione:
     * ad ogni accesso si riparte dal valore di default della tabella.
     */
    public function getDefaultTableRecordsPerPageSelectOption(): int | string
    {
        return $this->getTable()->getDefaultPaginationPageOption();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco fatture')
                // ->iconButton() // mostro solo icona
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get(); // recupero risultato della query
                    $filters = $livewire->tableFilters ?? []; // recupero i filtri
                    $search = $livewire->tableSearch ?? null; // recupero la ricerca

                    $fileName = 'Fatture_' . Carbon::today()->format('d-m-Y') . '.pdf';

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            $pdf = Pdf::loadHTML(
                                Blade::render('pdf.passive_invoices', [
                                    'invoices' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                            ->setPaper('A4', 'landscape')
                            ->setOptions([
                                'isHtml5ParserEnabled' => true, // Abilita parser HTML5 per CSS avanzato
                                'isPhpEnabled' => true, // Abilita PHP nel template
                                'isFontSubsettingEnabled' => true, // Ottimizza i font
                            ]);

                            echo $pdf->stream();
                        }, $fileName);
                })
                ->keyBindings(['alt+s']),
            ExportAction::make('esporta')
                ->icon('heroicon-s-table-cells')
                ->label('Esporta')
                ->tooltip('Esporta elenco fatture passive')
                ->color(Color::rgb('rgb(0,153,0)'))
                ->exporter(PassiveInvoiceExporter::class)
                ->keyBindings(['alt+e']),
            Actions\Action::make('passiveList')
                ->label('Scarica fatture passive')
                ->action(function (array $data) {
                    // Dispatch del job in background
                    \App\Jobs\DownloadPassiveInvoicesJob::dispatch(
                        $data,
                        Filament::getTenant()->id,
                        auth()->id()
                    );

                    Notification::make()
                        ->title('Scarico in elaborazione')
                        ->body('Lo scarico delle fatture passive è stato messo in coda. Riceverai una notifica al termine.')
                        ->info()
                        ->send();
                })
                ->form([
                    // Inserire filtri per gestire input opzionali
                    TextInput::make('password')
                        ->label('Password SOAP')
                        ->password()
                        ->revealable()
                        ->required(),
                    // TextInput::make('limit')
                    //     ->label('Numero fatture')
                ])
                ->requiresConfirmation(),
            // Actions\Action::make('getAttachments')
            //     ->label('Scarica allegati fatture passive')
            //     ->action(function (array $data) {
            //         $soapService = app(AndxorSoapService::class);
            //         try {
            //             $attached = $soapService->downloadAttachments($data);
            //             // $response = $soapService->downloadAttachments(['password' => 'W3iDWc3Q9w.3AUgd2zpz4']);

            //             $title = 'Allegati fatture passive scaricati con successo.';
            //             $msg = '';
            //             if ($attached == 1) {
            //                 $msg .= 'Trovati allegati di ' . $attached . ' fattura.<br> ';
            //             } elseif ($attached > 1) {
            //                 $msg .= 'Trovati allegati di ' . $attached . ' fatture.<br> ';
            //             }
            //             if (empty($msg)) {
            //                 $title = 'Procedura completata.';
            //                 $msg = 'Nessun allegato mancante trovato.';
            //             }

            //             Notification::make()
            //                 ->title($title)
            //                 ->body($msg)
            //                 ->success()
            //                 ->send();
            //         } catch (\Exception $e) {
            //             Notification::make()
            //                 ->title('Errore')
            //                 ->body($e->getMessage())
            //                 ->danger()
            //                 ->send();
            //         }
            //     })
            //     ->form([
            //         // Inserire filtri per gestire input opzionali
            //         TextInput::make('password')
            //             ->label('Password SOAP')
            //             ->password()
            //             ->revealable()
            //             ->required(),
            //         // TextInput::make('limit')
            //         //     ->label('Numero fatture')
            //     ])
            //     ->requiresConfirmation(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
