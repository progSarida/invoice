<?php

namespace App\Filament\Company\Resources\ContractDetailResource\Pages;

use App\Filament\Company\Resources\ContractDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContractDetail extends EditRecord
{
    protected static string $resource = ContractDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
