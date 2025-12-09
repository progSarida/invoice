<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

use App\Filament\Company\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        $currentClient = $this->record;
        $previousAClient = Client::where('denomination', '<', $currentClient->denomination)->orderBy('denomination', 'desc')->first();
        $nextAClient = Client::where('denomination', '>', $currentClient->denomination)->orderBy('denomination', 'asc')->first();
        $previousIClient = Client::where('id', '<', $currentClient->id)->orderBy('id', 'desc')->first();
        $nextIClient = Client::where('id', '>', $currentClient->id)->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento alfabetico
            Actions\Action::make('previous_a_client')
                ->label('Alfabetico prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousAClient) { return $previousAClient;})
                ->action(function () use ($previousAClient) {
                    $this->redirect(ClientResource::getUrl('view', ['record' => $previousAClient->id]));
                }),
            Actions\Action::make('next_a_client')
                ->label('Alfabetico succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextAClient) { return $nextAClient;})
                ->action(function () use ($nextAClient) {
                    $this->redirect(ClientResource::getUrl('view', ['record' => $nextAClient->id]));
                }),
            // Scorrimento id
            Actions\Action::make('previous_i_client')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIClient) { return $previousIClient;})
                ->action(function () use ($previousIClient) {
                    $this->redirect(ClientResource::getUrl('view', ['record' => $previousIClient->id]));
                }),
            Actions\Action::make('next_i_client')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIClient) { return $nextIClient;})
                ->action(function () use ($nextIClient) {
                    $this->redirect(ClientResource::getUrl('view', ['record' => $nextIClient->id]));
                }),
            Actions\EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
