<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;

class CreateNewActivePayments extends CreateRecord
{
    protected static string $resource = NewActivePaymentsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['validated'] ?? false) {
            $data['validation_date'] = now();
            $data['validated_by_user_id'] = Auth::id();
        }

        return $data;
    }
}
