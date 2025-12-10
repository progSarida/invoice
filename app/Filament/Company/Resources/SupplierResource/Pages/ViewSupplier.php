<?php

namespace App\Filament\Company\Resources\SupplierResource\Pages;

use App\Filament\Company\Resources\SupplierResource;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->denomination;
    }

    protected function getHeaderActions(): array
    {
        $currentSupplier = $this->record;
        $previousASupplier = Supplier::where('denomination', '<', $currentSupplier->denomination)->orderBy('denomination', 'desc')->first();
        $nextASupplier = Supplier::where('denomination', '>', $currentSupplier->denomination)->orderBy('denomination', 'asc')->first();
        $previousISupplier = Supplier::where('id', '<', $currentSupplier->id)->orderBy('id', 'desc')->first();
        $nextISupplier = Supplier::where('id', '>', $currentSupplier->id)->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento alfabetico
            Actions\Action::make('previous_a_supplier')
                ->label('Alfabetico prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousASupplier) { return $previousASupplier;})
                ->action(function () use ($previousASupplier) {
                    $this->redirect(SupplierResource::getUrl('view', ['record' => $previousASupplier->id]));
                }),
            Actions\Action::make('next_a_supplier')
                ->label('Alfabetico succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextASupplier) { return $nextASupplier;})
                ->action(function () use ($nextASupplier) {
                    $this->redirect(SupplierResource::getUrl('view', ['record' => $nextASupplier->id]));
                }),
            // Scorrimento id
            Actions\Action::make('previous_i_supplier')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousISupplier) { return $previousISupplier;})
                ->action(function () use ($previousISupplier) {
                    $this->redirect(SupplierResource::getUrl('view', ['record' => $previousISupplier->id]));
                }),
            Actions\Action::make('next_i_supplier')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextISupplier) { return $nextISupplier;})
                ->action(function () use ($nextISupplier) {
                    $this->redirect(SupplierResource::getUrl('view', ['record' => $nextISupplier->id]));
                }),
            Actions\EditAction::make(),
        ];
    }
}
