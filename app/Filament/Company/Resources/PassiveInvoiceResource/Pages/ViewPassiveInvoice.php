<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Models\PassiveInvoice;
use App\Models\PiValidation;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;

class ViewPassiveInvoice extends ViewRecord
{
    protected static string $resource = PassiveInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        $currentDoc = $this->record;
        $previousIDoc = PassiveInvoice::where('id', '<', $currentDoc->id)->orderBy('id', 'desc')->first();
        $nextIDoc = PassiveInvoice::where('id', '>', $currentDoc->id)->orderBy('id', 'asc')->first();
        $previousDDoc = PassiveInvoice::where('invoice_date', '<=', $currentDoc->invoice_date)
                ->where('id', '!=', $currentDoc->id)->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->first();
        $nextDDoc = PassiveInvoice::where('invoice_date', '>=', $currentDoc->invoice_date)
                ->where('id', '!=', $currentDoc->id)->orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento fatture
            Actions\Action::make('previous_doc')
                ->label('Data prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDoc) { return $previousDDoc;})
                ->action(function () use ($previousDDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('view', ['record' => $previousDDoc->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Data succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDoc) { return $nextDDoc;})
                ->action(function () use ($nextDDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('view', ['record' => $nextDDoc->id]));
                }),
            Actions\Action::make('previous_doc')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDoc) { return $previousIDoc;})
                ->action(function () use ($previousIDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('view', ['record' => $previousIDoc->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDoc) { return $nextIDoc;})
                ->action(function () use ($nextIDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('view', ['record' => $nextIDoc->id]));
                }),
            Actions\Action::make('validate')
                ->label('Valida pagamento')
                ->icon('fluentui-checkmark-starburst-20-o')
                ->requiresConfirmation()
                ->form([
                    Select::make('pi_validation_id')
                        ->label('')
                        ->options(
                            PiValidation::orderBy('order', 'asc')
                                ->pluck('name', 'id')
                                ->toArray()
                        )
                        ->default(fn (PassiveInvoice $record) => $record->pi_validation_id),
                ])
                ->action(function (PassiveInvoice $record, $data) {
                    $record->update([
                        'pi_validation_id' => $data['pi_validation_id']
                    ]);
                })
                ->color(Color::rgb('rgb(51, 204, 51)')),
            Actions\EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
