<?php

namespace App\Filament\Company\Resources\InsuranceResource\Pages;

use App\Filament\Company\Resources\InsuranceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInsurance extends CreateRecord
{
    protected static string $resource = InsuranceResource::class;

    public function getTitle(): string
    {
        return "Nuova compagnia assicurativa";
    }
}
