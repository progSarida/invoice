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
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
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
