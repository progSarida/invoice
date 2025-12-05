<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Enums\InvoiceReference;
use App\Filament\Company\Resources\InvoiceResource;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;

class ViewNewInvoice extends ViewRecord
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        $currentDoc = $this->record;
        $previousDoc = Invoice::where('id', '<', $currentDoc->id)->orderBy('id', 'desc')->first();
        $nextDoc = Invoice::where('id', '>', $currentDoc->id)->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento fatture
            Actions\Action::make('previous_doc')
                ->label('Precedente')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDoc) { return $previousDoc;})
                ->action(function () use ($previousDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $previousDoc->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Successiva')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDoc) { return $nextDoc;})
                ->action(function () use ($nextDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $nextDoc->id]));
                }),
            // Stampa fattura
            Actions\Action::make('stampa_pdf')
                ->label('Stampa')
                ->icon('heroicon-o-printer')
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->requiresConfirmation()
                ->modalHeading('Selezione stampa')
                ->modalDescription('Seleziona il tipo di stampa per la fattura')
                ->modalSubmitActionLabel('Selezione')
                ->form([
                    Select::make('selection')->label('Tipo stampa')
                        ->options([
                                "soft" => "Semplice",
                                "hard" => "Strutturata"
                            ]
                        ),
                ])
                ->action(function (Invoice $record, $data) {
                    $vats = $record->vatResume();                                           // Creazione array con dati riepiloghi IVA
                    // dd($vats);
                    $funds = $record->getFundBreakdown();                                   // Creazione array con dati casse previdenziali
                    // dd($funds);
                    if(count($funds) > 0)
                        $vats = $record->updateResume($vats, $funds);                       // Aggiorna l'array con dati riepiloghi IVA con i dati delle casse previdenziali
                    // dd($vats);
                    $grouped = collect($vats)                                               // Raggruppamento dati riepilochi IVA in base a aliquota
                        ->groupBy('%')
                        ->where('auto', false)
                        ->map(function ($items, $percent){
                            return [
                                '%' => $percent,
                                'taxable' => $items->sum('taxable'),
                                'vat' => $items->sum('vat'),
                                'total' => $items->sum('total'),
                                'norm' => $items->first()['norm'],
                                'free' => $items->first()['free'],
                            ];
                        })
                        ->values()
                        ->toArray();

                    if($data['selection'] == "hard"){
                        $pdf = Pdf::loadView('pdf.invoice', [
                            'invoice' => $record,
                            'vats' => $grouped,
                            'funds' => $funds,
                        ]);
                    }
                    else{
                        $pdf = Pdf::loadView('pdf.invoice_alt', [
                            'invoice' => $record,
                            'vats' => $grouped,
                            'funds' => $funds,
                        ]);
                    }

                    $pdf->setPaper('A4', 'portrait');

                    $pdf->setOptions(['margin-top' => 0]);

                    return response()->streamDownload(function () use ($pdf, $record) {
                        echo $pdf->output();
                    }, 'fattura-' . $record->printNumber() . '.pdf');
                }),
            Actions\EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
