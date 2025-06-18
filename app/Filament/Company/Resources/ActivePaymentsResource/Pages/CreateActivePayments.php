<?php

namespace App\Filament\Company\Resources\ActivePaymentsResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Company\Resources\ActivePaymentsResource;

class CreateActivePayments extends CreateRecord
{
    protected static string $resource = ActivePaymentsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registration_date'] = now();
        $data['registered_by_user_id'] = Auth::id();

        if ($data['validated'] ?? false) {
            $data['validation_date'] = now();
            $data['validated_by_user_id'] = Auth::id();
        }

        return $data;
    }
}
