<?php

namespace App\Filament\Company\Resources\InvoiceResource\Pages;

use App\Filament\Company\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $number = "";
        for($i=strlen($data['number']);$i<3;$i++)
        {
            $number.= "0";
        }
        $number = $number.$data['number'];
        $data['invoice_uid'] = $number." / 0".$data['section']." / ".$data['year'];

        return $data;
    }
}
