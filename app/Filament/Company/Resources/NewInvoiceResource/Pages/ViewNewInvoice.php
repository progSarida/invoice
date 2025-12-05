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
        $previousIDoc = Invoice::where('id', '<', $currentDoc->id)->orderBy('id', 'desc')->first();
        $nextIDoc = Invoice::where('id', '>', $currentDoc->id)->orderBy('id', 'asc')->first();
        $previousDDoc = Invoice::where('invoice_date', '<=', $currentDoc->invoice_date)
                ->where('id', '!=', $currentDoc->id)->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->first();
        $nextDDoc = Invoice::where('invoice_date', '>=', $currentDoc->invoice_date)
                ->where('id', '!=', $currentDoc->id)->orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento fatture
            Actions\Action::make('previous_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDoc) { return $previousDDoc;})
                ->action(function () use ($previousDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $previousDDoc->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDoc) { return $nextDDoc;})
                ->action(function () use ($nextDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $nextDDoc->id]));
                }),
            Actions\Action::make('previous_doc')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDoc) { return $previousIDoc;})
                ->action(function () use ($previousIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $previousIDoc->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDoc) { return $nextIDoc;})
                ->action(function () use ($nextIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $nextIDoc->id]));
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
