<?php

namespace App\Filament\Company\Resources\BailResource\Pages;

use App\Filament\Company\Resources\BailResource;
use App\Models\Agency;
use App\Models\Bail;
use App\Models\Insurance;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditBail extends EditRecord
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
        // // Precedente per bill_start: data precedente O stessa data con ID minore
        // $previousSBail = Bail::where(function ($query) use ($currentBail) {
        //         $query->where('bill_start', '<', $currentBail->bill_start)
        //             ->orWhere(function ($q) use ($currentBail) {
        //                 $q->where('bill_start', '=', $currentBail->bill_start)
        //                   ->where('id', '<', $currentBail->id);
        //             });
        //     })
        //     ->orderBy('bill_start', 'desc')->orderBy('id', 'desc')->first();
        // // Successivo per bill_start: data successiva O stessa data con ID maggiore
        // $nextSBail = Bail::where(function ($query) use ($currentBail) {
        //         $query->where('bill_start', '>', $currentBail->bill_start)
        //             ->orWhere(function ($q) use ($currentBail) {
        //                 $q->where('bill_start', '=', $currentBail->bill_start)
        //                   ->where('id', '>', $currentBail->id);
        //             });
        //     })
        //     ->orderBy('bill_start', 'asc')->orderBy('id', 'asc')->first();
        // // Precedente per bill_deadline: data precedente O stessa data con ID minore
        // $previousDBail = Bail::where(function ($query) use ($currentBail) {
        //         $query->where('bill_deadline', '<', $currentBail->bill_deadline)
        //             ->orWhere(function ($q) use ($currentBail) {
        //                 $q->where('bill_deadline', '=', $currentBail->bill_deadline)
        //                   ->where('id', '<', $currentBail->id);
        //             });
        //     })
        //     ->orderBy('bill_deadline', 'desc')->orderBy('id', 'desc')->first();
        // // Successivo per bill_deadline: data successiva O stessa data con ID maggiore
        // $nextDBail = Bail::where(function ($query) use ($currentBail) {
        //         $query->where('bill_deadline', '>', $currentBail->bill_deadline)
        //             ->orWhere(function ($q) use ($currentBail) {
        //                 $q->where('bill_deadline', '=', $currentBail->bill_deadline)
        //                   ->where('id', '>', $currentBail->id);
        //             });
        //     })
        //     ->orderBy('bill_deadline', 'asc')->orderBy('id', 'asc')->first();

        $currentBail = $this->record;

        // Verifica se esiste un lastDetail
        if (!$currentBail->lastDetail) {
            // Se non esiste lastDetail, non ci sono precedenti/successivi
            $previousSBail = null;
            $nextSBail = null;
            $previousDBail = null;
            $nextDBail = null;
        } else {
            $currentBillStart = $currentBail->lastDetail->bill_start;
            $currentBillDeadline = $currentBail->lastDetail->bill_deadline;
            $currentBailId = $currentBail->id;

            // Precedente per bill_start: data precedente O stessa data con ID minore
            $previousSBail = Bail::whereHas('bailDetails', function ($query) use ($currentBillStart, $currentBailId) {
                $query->where('bill_start', '<', $currentBillStart)
                    ->orWhere(function ($q) use ($currentBillStart, $currentBailId) {
                        $q->where('bill_start', '=', $currentBillStart)
                        ->where('bail_id', '<', $currentBailId);
                    });
            })
            ->with('lastDetail')
            ->get()
            ->filter(fn($bail) => $bail->lastDetail !== null)
            ->sortByDesc(function ($bail) {
                return [$bail->lastDetail->bill_start, $bail->id];
            })
            ->first();

            // Successivo per bill_start: data successiva O stessa data con ID maggiore
            $nextSBail = Bail::whereHas('bailDetails', function ($query) use ($currentBillStart, $currentBailId) {
                $query->where('bill_start', '>', $currentBillStart)
                    ->orWhere(function ($q) use ($currentBillStart, $currentBailId) {
                        $q->where('bill_start', '=', $currentBillStart)
                        ->where('bail_id', '>', $currentBailId);
                    });
            })
            ->with('lastDetail')
            ->get()
            ->filter(fn($bail) => $bail->lastDetail !== null)
            ->sortBy(function ($bail) {
                return [$bail->lastDetail->bill_start, $bail->id];
            })
            ->first();

            // Precedente per bill_deadline: data precedente O stessa data con ID minore
            $previousDBail = Bail::whereHas('bailDetails', function ($query) use ($currentBillDeadline, $currentBailId) {
                $query->where('bill_deadline', '<', $currentBillDeadline)
                    ->orWhere(function ($q) use ($currentBillDeadline, $currentBailId) {
                        $q->where('bill_deadline', '=', $currentBillDeadline)
                        ->where('bail_id', '<', $currentBailId);
                    });
            })
            ->with('lastDetail')
            ->get()
            ->filter(fn($bail) => $bail->lastDetail !== null)
            ->sortByDesc(function ($bail) {
                return [$bail->lastDetail->bill_deadline, $bail->id];
            })
            ->first();

            // Successivo per bill_deadline: data successiva O stessa data con ID maggiore
            $nextDBail = Bail::whereHas('bailDetails', function ($query) use ($currentBillDeadline, $currentBailId) {
                $query->where('bill_deadline', '>', $currentBillDeadline)
                    ->orWhere(function ($q) use ($currentBillDeadline, $currentBailId) {
                        $q->where('bill_deadline', '=', $currentBillDeadline)
                        ->where('bail_id', '>', $currentBailId);
                    });
            })
            ->with('lastDetail')
            ->get()
            ->filter(fn($bail) => $bail->lastDetail !== null)
            ->sortBy(function ($bail) {
                return [$bail->lastDetail->bill_deadline, $bail->id];
            })
            ->first();
        }

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
                ->action(fn() => $this->redirect(BailResource::getUrl('edit', ['record' => $previousSBail->id]))),

            Actions\Action::make('next_s_bail')
                ->label('Inizio succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn() => $nextSBail !== null)
                ->action(fn() => $this->redirect(BailResource::getUrl('edit', ['record' => $nextSBail->id]))),

            Actions\Action::make('previous_d_bail')
                ->label('Scadenza prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(fn() => $previousDBail !== null)
                ->action(fn() => $this->redirect(BailResource::getUrl('edit', ['record' => $previousDBail->id]))),

            Actions\Action::make('next_d_bail')
                ->label('Scadenza succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn() => $nextDBail !== null)
                ->action(fn() => $this->redirect(BailResource::getUrl('edit', ['record' => $nextDBail->id]))),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->color('success'),
            $this->getCancelFormAction(),
            $this->getResetFormAction(),
            $this->getDeleteFormAction()
                ->extraAttributes([
                    'class' => ' md:ml-auto md:w-auto ',
                ]),
        ];
    }

    protected function getDeleteFormAction()
    {
        return Actions\DeleteAction::make('delete')
                ->requiresConfirmation()
                ->modalHeading('Conferma eliminazione cauzione')
                ->modalDescription('Sei sicuro di voler eliminare questa cauzione? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/bails?')) {
                    return $this->previousUrl;
                }
                return BailResource::getUrl('index');
            });
    }

    protected function getResetFormAction(): Actions\Action
    {
        return Actions\Action::make('reset')
            ->label('Annulla')
            ->color('gray')
            ->action(function () {
                $this->data = $this->getRecord()->toArray();
                $this->fillForm();
            });
    }
}
