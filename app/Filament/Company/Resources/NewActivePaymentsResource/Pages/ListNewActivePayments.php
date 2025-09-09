<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Blade;

class ListNewActivePayments extends ListRecords
{
    protected static string $resource = NewActivePaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco pagamenti')
                ->color('primary')
                ->action(function ($livewire) {
                    // Recupera i record dalla query filtrata della tabella
                    $records = $livewire->getFilteredTableQuery()->get();
                    
                    // Recupera i filtri della tabella
                    $filters = $livewire->tableFilters ?? [];
                    
                    // Recupera la stringa di ricerca
                    $search = $livewire->tableSearch ?? null;
                    
                    // Genera il nome del file PDF
                    $fileName = 'Pagamenti_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';
                    
                    // Nota: Assicurati che la query della tabella in PaymentResource sia aggiornata per gestire
                    // 'contract_accrual_types' con whereJsonContains per filtrare i contratti in base agli ID in accrual_types.
                    // Esempio:
                    // ->when($filters['contract_accrual_types']['values'] ?? null, function ($query, $accrualTypes) {
                    //     foreach ($accrualTypes as $typeId) {
                    //         $query->whereHas('invoice.contract', fn($q) => $q->whereJsonContains('accrual_types', $typeId));
                    //     }
                    // })

                    return response()->streamDownload(function () use ($records, $search, $filters) {
                        // Renderizza il template Blade con i dati
                        $pdf = Pdf::loadHTML(
                            Blade::render('pdf.new_active_payments', [
                                'payments' => $records,
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
                    
                    // Invia notifica di conferma
                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                })
                // ->keyBindings(['alt+n'])
                ,
        ];
    }
}
