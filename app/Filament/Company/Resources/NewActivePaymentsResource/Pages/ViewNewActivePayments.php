<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use App\Models\ActivePayments;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNewActivePayments extends ViewRecord
{
    protected static string $resource = NewActivePaymentsResource::class;

    protected function getHeaderActions(): array
    {
        $currentPayment = $this->record;
        $previousDPayment = ActivePayments::where('payment_date', '<=', $currentPayment->payment_date)
                ->where('id', '!=', $currentPayment->id)->orderBy('payment_date', 'desc')->orderBy('id', 'desc')->first();
        $nextDPayment = ActivePayments::where('payment_date', '>=', $currentPayment->payment_date)
                ->where('id', '!=', $currentPayment->id)->orderBy('payment_date', 'asc')->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('previous_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDPayment) { return $previousDPayment;})
                ->action(function () use ($previousDPayment) {
                    $this->redirect(NewActivePaymentsResource::getUrl('view', ['record' => $previousDPayment->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDPayment) { return $nextDPayment;})
                ->action(function () use ($nextDPayment) {
                    $this->redirect(NewActivePaymentsResource::getUrl('view', ['record' => $nextDPayment->id]));
                }),
            Actions\EditAction::make(),
        ];
    }
}
