<?php

namespace App\Filament\Company\Resources\TransactionResource\Pages;

use App\Filament\Company\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        $currentTransaction = $this->record;
        // Precedente per ID: semplicemente ID minore
        $previousITransaction = Transaction::where('id', '<', $currentTransaction->id)->orderBy('id', 'desc')->first();
        // Successivo per ID: semplicemente ID maggiore
        $nextITransaction = Transaction::where('id', '>', $currentTransaction->id)->orderBy('id', 'asc')->first();
        // Precedente per date: data precedente O stessa data con ID minore
        $previousDTransaction = Transaction::where(function ($query) use ($currentTransaction) {
                $query->where('date', '<', $currentTransaction->date)
                    ->orWhere(function ($q) use ($currentTransaction) {
                        $q->where('date', '=', $currentTransaction->date)
                        ->where('id', '<', $currentTransaction->id);
                    });
            })
            ->orderBy('date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per date: data successiva O stessa data con ID maggiore
        $nextDTransaction = Transaction::where(function ($query) use ($currentTransaction) {
                $query->where('date', '>', $currentTransaction->date)
                    ->orWhere(function ($q) use ($currentTransaction) {
                        $q->where('date', '=', $currentTransaction->date)
                        ->where('id', '>', $currentTransaction->id);
                    });
            })
            ->orderBy('date', 'asc')->orderBy('id', 'asc')->first();

        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento
            Actions\Action::make('previous_d_transaction')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDTransaction) { return $previousDTransaction;})
                ->action(function () use ($previousDTransaction) {
                    $this->redirect(TransactionResource::getUrl('view', ['record' => $previousDTransaction->id]));
                }),
            Actions\Action::make('next_d_transaction')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDTransaction) { return $nextDTransaction;})
                ->action(function () use ($nextDTransaction) {
                    $this->redirect(TransactionResource::getUrl('view', ['record' => $nextDTransaction->id]));
                }),
            Actions\Action::make('previous_i_transaction')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousITransaction) { return $previousITransaction;})
                ->action(function () use ($previousITransaction) {
                    $this->redirect(TransactionResource::getUrl('view', ['record' => $previousITransaction->id]));
                }),
            Actions\Action::make('next_i_transaction')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextITransaction) { return $nextITransaction;})
                ->action(function () use ($nextITransaction) {
                    $this->redirect(TransactionResource::getUrl('view', ['record' => $nextITransaction->id]));
                }),
            Actions\EditAction::make(),
        ];
    }
}
