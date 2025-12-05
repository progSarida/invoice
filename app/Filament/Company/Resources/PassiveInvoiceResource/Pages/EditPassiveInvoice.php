<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Models\PassiveInvoice;
use App\Models\PiValidation;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Auth;

class EditPassiveInvoice extends EditRecord
{
    protected static string $resource = PassiveInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
            // Actions\DeleteAction::make()
            //     ->visible(fn (): bool => Auth::user()->isManager()),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
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
                ->modalHeading('Conferma eliminazione documento')
                ->modalDescription('Sei sicuro di voler eliminare questo documento? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/passive-invoices?')) {
                    return $this->previousUrl;
                }
                return PassiveInvoiceResource::getUrl('index');
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
