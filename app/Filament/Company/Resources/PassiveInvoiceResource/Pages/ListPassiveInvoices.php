<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Filament\Exports\PassiveInvoiceExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use App\Services\AndxorSoapService;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;

class ListPassiveInvoices extends ListRecords
{
    protected static string $resource = PassiveInvoiceResource::class;

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
                    $soapService = app(AndxorSoapService::class);
                    try {
                        $response = $soapService->downloadPassive($data);
                        // $response = $soapService->downloadPassive(['password' => 'W3iDWc3Q9w.3AUgd2zpz4']);

                        if (!$response instanceof \App\Models\PassiveDownload) {
                            throw new \Exception($response->getMessage());
                        }

                        $title = 'Fatture passive scaricate con successo.';
                        $msg = '';
                        if ($response->new_suppliers == 1) {
                            $msg .= 'Inserito ' . $response->new_suppliers . ' nuovo fornitore.<br> ';
                        } elseif ($response->new_suppliers > 1) {
                            $msg .= 'Inseriti ' . $response->new_suppliers . ' nuovi fornitori.<br> ';
                        }
                        if ($response->new_invoices == 1) {
                            $msg .= 'Scaricata ' . $response->new_invoices . ' nuova fattura passiva.';
                        } elseif ($response->new_invoices > 1) {
                            $msg .= 'Scaricate ' . $response->new_invoices . ' nuove fatture passive.';
                        }
                        if (empty($msg)) {
                            $title = 'Procedura completata.';
                            $msg = 'Nessuna nuova fattura o fornitore scaricato.';
                        }

                        Notification::make()
                            ->title($title)
                            ->body($msg)
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
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
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
