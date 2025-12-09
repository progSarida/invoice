<?php

namespace App\Filament\Company\Resources\DeadlineResource\Pages;

use App\Filament\Company\Resources\DeadlineResource;
use App\Models\Deadline;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeadline extends EditRecord
{
    protected static string $resource = DeadlineResource::class;

    protected function getHeaderActions(): array
    {
        $currentDeadline = $this->record;
        // Precedente per ID: semplicemente ID minore
        $previousIDeadline = Deadline::where('id', '<', $currentDeadline->id)->orderBy('id', 'desc')->first();
        // Successivo per ID: semplicemente ID maggiore
        $nextIDeadline = Deadline::where('id', '>', $currentDeadline->id)->orderBy('id', 'asc')->first();
        // Precedente per date: data precedente O stessa data con ID minore
        $previousDDeadline = Deadline::where(function ($query) use ($currentDeadline) {
                $query->where('date', '<', $currentDeadline->date)
                    ->orWhere(function ($q) use ($currentDeadline) {
                        $q->where('date', '=', $currentDeadline->date)
                        ->where('id', '<', $currentDeadline->id);
                    });
            })
            ->orderBy('date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per date: data successiva O stessa data con ID maggiore
        $nextDDeadline = Deadline::where(function ($query) use ($currentDeadline) {
                $query->where('date', '>', $currentDeadline->date)
                    ->orWhere(function ($q) use ($currentDeadline) {
                        $q->where('date', '=', $currentDeadline->date)
                        ->where('id', '>', $currentDeadline->id);
                    });
            })
            ->orderBy('date', 'asc')->orderBy('id', 'asc')->first();

        return [
            // Scorrimento
            Actions\Action::make('previous_d_deadline')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDeadline) { return $previousDDeadline;})
                ->action(function () use ($previousDDeadline) {
                    $this->redirect(DeadlineResource::getUrl('edit', ['record' => $previousDDeadline->id]));
                }),
            Actions\Action::make('next_d_deadline')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDeadline) { return $nextDDeadline;})
                ->action(function () use ($nextDDeadline) {
                    $this->redirect(DeadlineResource::getUrl('edit', ['record' => $nextDDeadline->id]));
                }),
            Actions\Action::make('previous_i_deadline')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDeadline) { return $previousIDeadline;})
                ->action(function () use ($previousIDeadline) {
                    $this->redirect(DeadlineResource::getUrl('edit', ['record' => $previousIDeadline->id]));
                }),
            Actions\Action::make('next_i_deadline')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDeadline) { return $nextIDeadline;})
                ->action(function () use ($nextIDeadline) {
                    $this->redirect(DeadlineResource::getUrl('edit', ['record' => $nextIDeadline->id]));
                }),
            // Actions\DeleteAction::make(),
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
                ->modalHeading('Conferma eliminazione scadenza')
                ->modalDescription('Sei sicuro di voler eliminare questa scadenza? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/deadlines?')) {
                    return $this->previousUrl;
                }
                return DeadlineResource::getUrl('index');
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
