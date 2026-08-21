<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Models\DocType;
use App\Models\PassiveInvoice;
use App\Models\PiValidation;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class EditPassiveInvoice extends EditRecord
{
    protected static string $resource = PassiveInvoiceResource::class;

    #[On('refreshEditPage')]
    public function refreshRecord(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }

    public function getTitle(): string | Htmlable
    {
        $number = $this->record->number;
        $doc = DocType::where('name', $this->record->doc_type)->select('description')->first()->description;
        $date = Carbon::parse($this->record->invoice_date)->format('d/m/Y');

        // return $doc . " n. " . $number . " del " . $date;
        return "n.ro " . $number . " del " . $date;
    }

    protected function getHeaderActions(): array
    {
         $currentDoc = $this->record;
        // Precedente per ID: semplicemente ID minore
        $previousIDoc = PassiveInvoice::where('id', '<', $currentDoc->id)->orderBy('id', 'desc')->first();
        // Successivo per ID: semplicemente ID maggiore
        $nextIDoc = PassiveInvoice::where('id', '>', $currentDoc->id)->orderBy('id', 'asc')->first();
        // Precedente per invoice_date: data precedente O stessa data con ID minore
        $previousDDoc = PassiveInvoice::where(function ($query) use ($currentDoc) {
                $query->where('invoice_date', '<', $currentDoc->invoice_date)
                    ->orWhere(function ($q) use ($currentDoc) {
                        $q->where('invoice_date', '=', $currentDoc->invoice_date)
                        ->where('id', '<', $currentDoc->id);
                    });
            })
            ->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per invoice_date: data successiva O stessa data con ID maggiore
        $nextDDoc = PassiveInvoice::where(function ($query) use ($currentDoc) {
                $query->where('invoice_date', '>', $currentDoc->invoice_date)
                    ->orWhere(function ($q) use ($currentDoc) {
                        $q->where('invoice_date', '=', $currentDoc->invoice_date)
                        ->where('id', '>', $currentDoc->id);
                    });
            })
            ->orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->first();

        return [
            // Scorrimento fatture
            Actions\Action::make('previous_d_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDoc) { return $previousDDoc;})
                ->action(function () use ($previousDDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('edit', ['record' => $previousDDoc->id]));
                }),
            Actions\Action::make('next_d_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDoc) { return $nextDDoc;})
                ->action(function () use ($nextDDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('edit', ['record' => $nextDDoc->id]));
                }),
            Actions\Action::make('previous_i_doc')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDoc) { return $previousIDoc;})
                ->action(function () use ($previousIDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('edit', ['record' => $previousIDoc->id]));
                }),
            Actions\Action::make('next_i_doc')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDoc) { return $nextIDoc;})
                ->action(function () use ($nextIDoc) {
                    $this->redirect(PassiveInvoiceResource::getUrl('edit', ['record' => $nextIDoc->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('validate')
                    ->label('Valida fattura')
                    ->icon('fluentui-checkmark-starburst-20-o')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => !$record?->pi_validation_id)
                    ->form([
                        Select::make('pi_validation_id')
                            ->label('')
                            ->placeholder('Da validare')
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
                Actions\Action::make('no_validate')
                    ->label('Annulla validazione')
                    ->icon('fluentui-dismiss-circle-20-o')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma annullamento validazione')
                    ->modalDescription('Sei sicuro di voler annullare la validazione di questa fattura?') 
                    ->visible(fn ($record) => $record?->pi_validation_id)
                    ->action(function (PassiveInvoice $record) {
                        // Con pagamenti già imputati la fattura non può tornare "Da validare":
                        // resterebbero pagamenti su una fattura in uno stato che non ne consente l'inserimento
                        $paymentsCount = $record->passivePayments()->count();

                        if ($paymentsCount > 0) {
                            Notification::make()
                                ->title('Annullamento validazione non consentito')
                                ->body($paymentsCount === 1
                                    ? "Alla fattura è associato 1 pagamento. Per annullare la validazione è necessario prima eliminare il pagamento."
                                    : "Alla fattura sono associati {$paymentsCount} pagamenti. Per annullare la validazione è necessario prima eliminarli."
                                )
                                ->danger()
                                ->duration(8000)
                                ->send();

                            return;
                        }

                        $record->update([ 'pi_validation_id' => null ]);
                    })
                    ->color('danger'),
                // Actions\DeleteAction::make()
                //     ->visible(fn (): bool => Auth::user()->isManager()),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->visible(fn($record) => !$record->downloaded)
                ->color('success'),
            $this->getCancelFormAction(),
            $this->getResetFormAction(),
            $this->getDeleteFormAction()
                ->visible(fn($record) => !$record->downloaded)
                ->extraAttributes([
                    'class' => ' md:ml-auto md:w-auto ',
                ]),
        ];
    }

    protected function getDeleteFormAction()
    {
        return Actions\DeleteAction::make('delete')
                ->requiresConfirmation()
                // Se la fattura non è eliminabile (origine SdI, validazione o elementi
                // collegati) il modale spiega il motivo e non espone il pulsante di conferma.
                ->modalHeading(fn () => $this->getRecord()->isDeletable()
                    ? 'Conferma eliminazione documento'
                    : 'Documento non eliminabile')
                ->modalDescription(fn () => $this->getRecord()->getDeletionBlockReason()
                    ?? 'Sei sicuro di voler eliminare questo documento? Questa azione non può essere annullata.')
                ->modalSubmitAction(fn () => $this->getRecord()->isDeletable() ? null : false)
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel(fn () => $this->getRecord()->isDeletable() ? 'Annulla' : 'Chiudi')
                ->before(function (Actions\DeleteAction $action) {
                    $reason = $this->getRecord()->getDeletionBlockReason();

                    if ($reason) {
                        Notification::make()
                            ->title('Eliminazione non consentita')
                            ->body($reason)
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->cancel();
                    }
                });
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
