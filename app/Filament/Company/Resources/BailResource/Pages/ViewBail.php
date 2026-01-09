<?php

namespace App\Filament\Company\Resources\BailResource\Pages;

use App\Filament\Company\Resources\BailResource;
use App\Models\Agency;
use App\Models\Bail;
use App\Models\Insurance;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewBail extends ViewRecord
{
    protected static string $resource = BailResource::class;

    public function getTitle(): string | Htmlable
    {
        $number = $this->record->bill_number;
        $agency = Agency::find($this->record->agency_id)->name;

        // return 'Polizza n. ' . $number . " con " . $agency;
        return 'Polizza n. ' . $number;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        $currentBail = $this->record;
        // Precedente per bill_start: data precedente O stessa data con ID minore
        $previousSBail = Bail::where(function ($query) use ($currentBail) {
                $query->where('bill_start', '<', $currentBail->bill_start)
                    ->orWhere(function ($q) use ($currentBail) {
                        $q->where('bill_start', '=', $currentBail->bill_start)
                          ->where('id', '<', $currentBail->id);
                    });
            })
            ->orderBy('bill_start', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per bill_start: data successiva O stessa data con ID maggiore
        $nextSBail = Bail::where(function ($query) use ($currentBail) {
                $query->where('bill_start', '>', $currentBail->bill_start)
                    ->orWhere(function ($q) use ($currentBail) {
                        $q->where('bill_start', '=', $currentBail->bill_start)
                          ->where('id', '>', $currentBail->id);
                    });
            })
            ->orderBy('bill_start', 'asc')->orderBy('id', 'asc')->first();
        // Precedente per bill_deadline: data precedente O stessa data con ID minore
        $previousDBail = Bail::where(function ($query) use ($currentBail) {
                $query->where('bill_deadline', '<', $currentBail->bill_deadline)
                    ->orWhere(function ($q) use ($currentBail) {
                        $q->where('bill_deadline', '=', $currentBail->bill_deadline)
                          ->where('id', '<', $currentBail->id);
                    });
            })
            ->orderBy('bill_deadline', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per bill_deadline: data successiva O stessa data con ID maggiore
        $nextDBail = Bail::where(function ($query) use ($currentBail) {
                $query->where('bill_deadline', '>', $currentBail->bill_deadline)
                    ->orWhere(function ($q) use ($currentBail) {
                        $q->where('bill_deadline', '=', $currentBail->bill_deadline)
                          ->where('id', '>', $currentBail->id);
                    });
            })
            ->orderBy('bill_deadline', 'asc')->orderBy('id', 'asc')->first();

        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),

            Actions\Action::make('previous_s_bail')
                ->label('Inizio prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(fn() => $previousSBail !== null)
                ->action(fn() => $this->redirect(BailResource::getUrl('view', ['record' => $previousSBail->id]))),

            Actions\Action::make('next_s_bail')
                ->label('Inizio succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn() => $nextSBail !== null)
                ->action(fn() => $this->redirect(BailResource::getUrl('view', ['record' => $nextSBail->id]))),

            Actions\Action::make('previous_d_bail')
                ->label('Scadenza prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(fn() => $previousDBail !== null)
                ->action(fn() => $this->redirect(BailResource::getUrl('view', ['record' => $previousDBail->id]))),

            Actions\Action::make('next_d_bail')
                ->label('Scadenza succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn() => $nextDBail !== null)
                ->action(fn() => $this->redirect(BailResource::getUrl('view', ['record' => $nextDBail->id]))),

            Actions\EditAction::make(),
        ];
    }
}
