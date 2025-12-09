<?php

namespace App\Filament\Company\Resources\DeadlineResource\Pages;

use App\Filament\Company\Resources\DeadlineResource;
use App\Models\Deadline;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeadline extends ViewRecord
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
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento
            Actions\Action::make('previous_d_deadline')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDeadline) { return $previousDDeadline;})
                ->action(function () use ($previousDDeadline) {
                    $this->redirect(DeadlineResource::getUrl('view', ['record' => $previousDDeadline->id]));
                }),
            Actions\Action::make('next_d_deadline')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDeadline) { return $nextDDeadline;})
                ->action(function () use ($nextDDeadline) {
                    $this->redirect(DeadlineResource::getUrl('view', ['record' => $nextDDeadline->id]));
                }),
            Actions\Action::make('previous_i_deadline')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDeadline) { return $previousIDeadline;})
                ->action(function () use ($previousIDeadline) {
                    $this->redirect(DeadlineResource::getUrl('view', ['record' => $previousIDeadline->id]));
                }),
            Actions\Action::make('next_i_deadline')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDeadline) { return $nextIDeadline;})
                ->action(function () use ($nextIDeadline) {
                    $this->redirect(DeadlineResource::getUrl('view', ['record' => $nextIDeadline->id]));
                }),
            Actions\EditAction::make(),
        ];
    }
}
