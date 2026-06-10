<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Pages;

use App\Filament\Company\Resources\PostalExpenseResource;
use App\Models\PostalExpense;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPostalExpense extends ViewRecord
{
    protected static string $resource = PostalExpenseResource::class;

    protected function getHeaderActions(): array
    {
        $currentExpense = $this->record;
        // Precedente per act_date: data precedente O stessa data con ID minore
        $previousSBail = PostalExpense::where(function ($query) use ($currentExpense) {
                $query->where('act_date', '<', $currentExpense->act_date)
                    ->orWhere(function ($q) use ($currentExpense) {
                        $q->where('act_date', '=', $currentExpense->act_date)
                          ->where('id', '<', $currentExpense->id);
                    });
            })
            ->orderBy('act_date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per act_date: data successiva O stessa data con ID maggiore
        $nextSBail = PostalExpense::where(function ($query) use ($currentExpense) {
                $query->where('act_date', '>', $currentExpense->act_date)
                    ->orWhere(function ($q) use ($currentExpense) {
                        $q->where('act_date', '=', $currentExpense->act_date)
                          ->where('id', '>', $currentExpense->id);
                    });
            })
            ->orderBy('act_date', 'asc')->orderBy('id', 'asc')->first();

        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('previous_expense')
                ->label('Atto prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(fn() => $previousSBail !== null)
                ->action(fn() => $this->redirect(PostalExpenseResource::getUrl('view', ['record' => $previousSBail->id]))),
            Actions\Action::make('next_expense')
                ->label('Atto succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn() => $nextSBail !== null)
                ->action(fn() => $this->redirect(PostalExpenseResource::getUrl('view', ['record' => $nextSBail->id]))),
            Actions\EditAction::make(),
        ];
    }
}
