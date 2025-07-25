<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use App\Filament\Company\Resources\NewContractResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNewContract extends CreateRecord
{
    protected static string $resource = NewContractResource::class;

    public function getTitle(): string
    {
        return "Nuovo contratto";
    }
}
