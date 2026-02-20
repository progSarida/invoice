<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Enums\InvoiceReference;
use App\Enums\SdiStatus;
use App\Filament\Company\Resources\InvoiceResource;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\DocType;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;

class ViewNewInvoice extends ViewRecord
{
    protected static string $resource = NewInvoiceResource::class;

    public function getTitle(): string | Htmlable
    {
        $number = $this->record->getNewInvoiceNumber();
        $doc = DocType::find($this->record->doc_type_id)->description;
        $date = Carbon::parse($this->record->invoice_date)->format('d/m/Y');

        // return $doc . " n. " . $number . " del " . $date;
        return "n.ro " . $number . " del " . $date;
    }

    protected function getHeaderActions(): array
    {
        $currentDoc = $this->record;
        // Precedente per ID: semplicemente ID minore
        $previousIDoc = Invoice::where('id', '<', $currentDoc->id)->orderBy('id', 'desc')->first();
        // Successivo per ID: semplicemente ID maggiore
        $nextIDoc = Invoice::where('id', '>', $currentDoc->id)->orderBy('id', 'asc')->first();
        // Precedente per invoice_date: data precedente O stessa data con ID minore
        $previousDDoc = Invoice::where(function ($query) use ($currentDoc) {
                $query->where('invoice_date', '<', $currentDoc->invoice_date)
                    ->orWhere(function ($q) use ($currentDoc) {
                        $q->where('invoice_date', '=', $currentDoc->invoice_date)
                        ->where('id', '<', $currentDoc->id);
                    });
            })
            ->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per invoice_date: data successiva O stessa data con ID maggiore
        $nextDDoc = Invoice::where(function ($query) use ($currentDoc) {
                $query->where('invoice_date', '>', $currentDoc->invoice_date)
                    ->orWhere(function ($q) use ($currentDoc) {
                        $q->where('invoice_date', '=', $currentDoc->invoice_date)
                        ->where('id', '>', $currentDoc->id);
                    });
            })
            ->orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->first();

        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento fatture
            Actions\Action::make('previous_d_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDoc) { return $previousDDoc;})
                ->action(function () use ($previousDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $previousDDoc->id]));
                }),
            Actions\Action::make('next_d_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDoc) { return $nextDDoc;})
                ->action(function () use ($nextDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $nextDDoc->id]));
                }),
            Actions\Action::make('previous_i_doc')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDoc) { return $previousIDoc;})
                ->action(function () use ($previousIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $previousIDoc->id]));
                }),
            Actions\Action::make('next_i_doc')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDoc) { return $nextIDoc;})
                ->action(function () use ($nextIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $nextIDoc->id]));
                }),
            Actions\ActionGroup::make([
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
                            )
                            ->default('soft'),
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
                Actions\Action::make('manage_reject')
                    ->label('Gestisci rifiuto')
                    ->icon('hugeicons-file-management')
                    ->color(Color::rgb('rgb(255, 0, 0)'))
                    ->visible(fn ($record) => $record->sdi_status == SdiStatus::RIFIUTATA)
                    ->requiresConfirmation()
                    ->modalHeading('Selezione tipo rifiuto')
                    ->modalDescription('Seleziona il tipo di rifiuto per il documento')
                    ->modalSubmitActionLabel('Conferma')
                    ->form([
                        Select::make('selection')
                            ->label('')
                            ->options(
                                collect(SdiStatus::cases())
                                    ->filter(fn (SdiStatus $status) => $status->showReject())
                                    ->mapWithKeys(fn ($status) => [
                                        $status->value => $status->getLabel() ?? $status->name
                                    ])
                            ),
                    ])
                    ->action(function (Invoice $record, $data) {
                        $record->update([
                            'sdi_status' => $data['selection'],
                        ]);

                        Notification::make()
                            ->title('Stato SDI aggiornato')
                            ->body('Lo stato SDI del documento ' . $record->getNewInvoiceNumber() . ' è stato aggiornato a ' . $record->sdi_status->getLabel())
                            ->icon('heroicon-o-check-circle')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('manage_discard')
                    ->label('Gestisci scarto')
                    ->icon('hugeicons-file-management')
                    ->color(Color::rgb('rgb(255, 0, 0)'))
                    ->visible(fn ($record) => $record->sdi_status == SdiStatus::SCARTATA)
                    ->requiresConfirmation()
                    ->modalHeading('Selezione tipo scarto')
                    ->modalDescription('Seleziona il tipo di scarto per il documento')
                    ->modalSubmitActionLabel('Conferma')
                    ->form([
                        Select::make('selection')
                            ->label('')
                            ->options(
                                collect(SdiStatus::cases())
                                    ->filter(fn (SdiStatus $status) => $status->showDiscard())
                                    ->mapWithKeys(fn ($status) => [
                                        $status->value => $status->getLabel() ?? $status->name
                                    ])
                            ),
                    ])
                    ->action(function (Invoice $record, $data) {
                        $record->update([
                            'sdi_status' => $data['selection'],
                        ]);

                        Notification::make()
                            ->title('Stato SDI aggiornato')
                            ->body('Lo stato SDI del documento ' . $record->getNewInvoiceNumber() . ' è stato aggiornato a ' . $record->sdi_status->getLabel())
                            ->icon('heroicon-o-check-circle')
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make()
                    ->hidden(fn($record) => $record->sdi_status != SdiStatus::DA_INVIARE),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
