<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use Filament\Actions;
use App\Models\Invoice;
use App\Models\Sectional;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Company\Resources\NewInvoiceResource;

class EditNewInvoice extends EditRecord
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('stampa_pdf')
                ->label('Stampa PDF')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->action(function (Invoice $record) {
                    return response()->streamDownload(function () use ($record) {
                        echo Pdf::loadView('pdf.invoice', ['invoice' => $record])->stream();
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

        $number = "";
        $sectional = Sectional::find($data['sectional_id'])->description;
        for($i=strlen($data['number']);$i<3;$i++)
        {
            $number.= "0";
        }
        $number = $number.$data['number'];
        $data['invoice_uid'] = $number."/".$sectional."/".$data['year'];

        return $data;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
