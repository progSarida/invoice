<?php

namespace App\Filament\Company\Resources\ActivePaymentsResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Company\Resources\ActivePaymentsResource;

class EditActivePayments extends EditRecord
{
    protected static string $resource = ActivePaymentsResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['validated'] && !$this->record->validated) {
            $data['validation_date'] = now();
            $data['validated_by_user_id'] = Auth::id();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn () => $this->record->validated),
            Actions\ForceDeleteAction::make()
                ->disabled(fn () => $this->record->validated),
            Actions\RestoreAction::make()
                ->disabled(fn () => $this->record->validated),
        ];
    }
}
