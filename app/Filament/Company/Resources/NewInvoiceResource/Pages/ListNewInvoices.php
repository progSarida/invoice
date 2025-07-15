<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use Carbon\Carbon;
use Filament\Actions;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\ExportAction;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Exports\NewInvoiceExporter;
use App\Filament\Company\Resources\NewInvoiceResource;

class ListNewInvoices extends ListRecords
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->hidden(function () {
                    // Fatture rifiutate
                    $refusedHide = false;
                    $refused = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'rifiutata');
                    $refusedE = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'rifiuto_emesso');
                    // Fatture scartate
                    $discardedHide = false;
                    $discarded = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'scartata');
                    // Fatture non inviate da due giorni
                    $lateHide = false;
                    $late = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'da_inviare')->where('invoice_date', '<', Carbon::now()->subDays(2));
                    // Fatture senza esito inviate da più di 3 giorni
                    $silentHide = false;
                    $silent = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'inviata')->where('sdi_date', '<', Carbon::now()->subDays(3));

                    // $discardedE = \App\Models\Invoice::where('sdi_status', 'scartata');

                    // Usa un identificatore unico per evitare notifiche duplicate
                    // $notificationIds = [
                    //     'refused_block',
                    //     'refused_status',
                    //     'discarded_block',
                    //     'discarded_status',
                    // ];

                    // Resetta le notifiche esistenti (se necessario)
                    // foreach ($notificationIds as $id) {
                    //     Notification::make($id)->danger()->duration(1)->send(); // Sovrascrive notifiche esistenti con lo stesso ID
                    // }

                    if ($silent->count() > 0) {                                                                   // blocco per fatture senza esito inviate da più di 3 giorni
                        Notification::make('silent_status')
                            ->title('Sono presenti fatture senza esito da oltre 3 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $silentHide = true;
                    }

                    if ($late->count() > 0) {                                                                   // blocco per fatture con data vecchia di più di 2 giorni
                        Notification::make('late_status')
                            ->title('Sono presenti fatture da inviare da almeno 2 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $lateHide = true;
                    }

                    if ($refusedE->count() > 0) {                                                               // linkk fatture rifiutate
                        $invoicesR = $refusedE->get();
                        foreach ($invoicesR as $index => $el) {
                            if (!\App\Models\Invoice::where('parent_id', $el->id)->exists()) {
                                Notification::make('refused_credit_note_' . $el->id)
                                    ->title('Emettere la nota di credito per la fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year)
                                    ->color('gray')
                                    ->icon('phosphor-warning-circle-light')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai alla fattura')
                                            ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                        }
                        $refusedHide = true;
                    }

                    if ($refused->count() > 0) {                                                                // blocco fatture rifiutate
                        Notification::make('refused_status')
                            ->title('Sono presenti fatture rifiutate<br>(Status: NE EC02 - Rifiuto)<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $refusedHide = true;
                    }

                    if ($discarded->count() > 0) {                                                              // link fatture scartate
                        $invoicesD = $discarded->get();
                        foreach ($invoicesD as $index => $el) {
                            $daysLeft = now()->diffInDays($el->invoice_date, true);
                            // if($el->id = 6348) dd($daysLeft);
                            if($daysLeft <= 12){
                                Notification::make('discarded_manage_' . $el->id)
                                    ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                            Correggere i dati errati e reinviare<br>")
                                    ->color('gray')
                                    ->icon('phosphor-warning-circle-light')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai alla fattura')
                                            ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                            else{
                                Notification::make('discarded_manage_' . $el->id)
                                    ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                            Modificare stato (in Scarto validato) ed emettere una nuova fattura<br>[Fattura collegata alla numero
                                            " . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . " del " . \Carbon\Carbon::parse($el->invoice_date)->format('d/m/Y') . " scartata dallo SDI]")
                                    ->color('gray')
                                    ->icon('phosphor-warning-circle-light')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai alla fattura')
                                            ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                        }
                        $refusedHide = true;
                    }

                    if ($discarded->count() > 0) {                                                              // blocco per fatture scartate
                        Notification::make('discarded_status')
                            ->title('Sono presenti fatture scartate<br>(Status: NS - Notifica di scarto)<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $discardedHide = true;
                    }

                    return ($refusedHide || $discardedHide || $lateHide || $silentHide);
                })
                // ->keyBindings(['alt+n'])
                ,
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco fatture')
                // ->iconButton() // mostro solo icona
                ->color('primary')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get(); // recupero risultato della query
                    $filters = $livewire->tableFilters ?? []; // recupero i filtri
                    $search = $livewire->tableSearch ?? null; // recupero la ricerca

                    $fileName = 'Fatture_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                                Blade::render('pdf.new_invoices', [
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

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                })
                // ->keyBindings(['alt+s'])
                ,
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->color('primary')
                ->exporter(NewInvoiceExporter::class)
                // ->keyBindings(['alt+e'])
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
