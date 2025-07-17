<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use Filament\Actions;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Sectional;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Company\Resources\NewInvoiceResource;

class EditNewInvoice extends EditRecord
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            // Actions\Action::make('stampa_pdf')
            //     ->label('Stampa PDF')
            //     ->icon('heroicon-o-printer')
            //     ->color('primary')
            //     ->action(function (Invoice $record) {
            //         $vats = $record->vatResume();
            //         $grouped = collect($vats)
            //             ->groupBy('%')
            //             ->map(function ($items, $percent) {
            //                 return [
            //                     '%' => $percent,
            //                     'taxable' => $items->sum('taxable'),
            //                     'vat' => $items->sum('vat'),
            //                     'total' => $items->sum('total'),
            //                     'norm' => $items->first()['norm'],
            //                     'free' => $items->first()['free'],
            //                 ];
            //             })
            //             ->values()
            //             ->toArray();
            //         return response()->streamDownload(function () use ($record, $grouped) {
            //             echo Pdf::loadView('pdf.invoice', [ 'invoice' => $record, 'vats' => $grouped ])->stream();
            //         }, 'fattura-' . $record->printNumber() . '.pdf');
            //     }),

            // Actions\Action::make('stampa_pdf')
            //     ->label('Stampa PDF')
            //     ->icon('heroicon-o-printer')
            //     ->color('primary')
            //     ->action(function (Invoice $record) {
            //         $vats = $record->vatResume();
            //         $grouped = collect($vats)
            //             ->groupBy('%')
            //             ->map(function ($items, $percent) {
            //                 return [
            //                     '%' => $percent,
            //                     'taxable' => $items->sum('taxable'),
            //                     'vat' => $items->sum('vat'),
            //                     'total' => $items->sum('total'),
            //                     'norm' => $items->first()['norm'],
            //                     'free' => $items->first()['free'],
            //                 ];
            //             })
            //             ->values()
            //             ->toArray();
            //         return response()->streamDownload(function () use ($record, $grouped) {
            //             echo Pdf::loadView('pdf.invoice', [
            //                 'invoice' => $record,
            //                 'vats' => $grouped
            //             ])->output(); // <- usa output() al posto di stream()
            //         }, 'fattura-' . $record->printNumber() . '.pdf');
            //     }),

            Actions\Action::make('stampa_pdf')
                ->label('Stampa PDF')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->action(function (Invoice $record) {
                    $vats = $record->vatResume();                                   // Creazione array con dati riepiloghi IVA
                    // dd($vats);
                    $funds = $record->getFundBreakdown();                           // Creazione array con dati casse previdenziali
                    // dd($funds);
                    if(count($funds) > 0)
                        $vats = $record->updateResume($vats, $funds);               // Aggiorna l'array con dati riepiloghi IVA con i dati delle casse previdenziali
                    // dd($vats);
                    $grouped = collect($vats)                                       // Raggruppamento dati riepilochi IVA in base a aliquota
                        ->groupBy('%')
                        ->map(function ($items, $percent) {
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

                    $pdf = Pdf::loadView('pdf.invoice', [
                        'invoice' => $record,
                        'vats' => $grouped,
                        'funds' => $funds,
                    ]);

                    $pdf->setPaper('A4', 'portrait');

                    $pdf->setOptions(['margin-top' => 0]);

                    return response()->streamDownload(function () use ($pdf, $record) {
                        echo $pdf->output();
                    }, 'fattura-' . $record->printNumber() . '.pdf');
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // $number = "";
        // for($i=strlen($data['number']);$i<3;$i++)
        // {
        //     $number.= "0";
        // }
        // $number = $number.$data['number'];
        // $data['invoice_uid'] = $number." / 0".$data['section']." / ".$data['year'];

        if($data['art_73']) {
            $number = "";
            // $date = $data['invoice_date'];
            $date = \Carbon\Carbon::parse($data['invoice_date'])->format('Y-m-d');
            for($i=strlen($data['number']);$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$data['number'];
            $data['invoice_uid'] = $number."/".$date;
        }
        else{
            $number = "";
            $sectional = Sectional::find($data['sectional_id'])->description;
            for($i=strlen($data['number']);$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$data['number'];
            $data['invoice_uid'] = $number."/".$sectional."/".$data['year'];
        }

        return $data;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
