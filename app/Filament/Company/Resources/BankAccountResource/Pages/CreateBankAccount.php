<?php

namespace App\Filament\Company\Resources\BankAccountResource\Pages;

use App\Filament\Company\Resources\BankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;
}
