<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use App\Services\AndxorSoapService;
use Filament\Forms\Components\TextInput;

class ListPassiveInvoices extends ListRecords
{
    protected static string $resource = PassiveInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('passiveList')
                ->label('Scarica fatture passive')
                ->action(function (array $data) {
                    $soapService = app(AndxorSoapService::class);
                    try {
                        // $response = $soapService->downloadPassive($data);
                        $response = $soapService->downloadPassive(['password' => 'W3iDWc3Q9w.3AUgd2zpz4']);
                        Notification::make()
                            ->title('Fatture passive scaricate con successo.')
                            ->body(function () use ($response) {
                                    $msg = '';
                                    if($response['supplierNumber'] = 1)
                                        $msg .= 'Inserito ' . $response['supplierNumber'] . ' nuovo fornitore\n';
                                    else if($response['supplierNumber'] > 1)
                                        $msg .= 'Inseriti ' . $response['supplierNumber'] . ' nuovi fornitori\n';
                                    if($response['supplierNumber'] > 0)
                                        $msg .= 'Scaricate ' . $response['invoiceNumber'] . ' nuove fatture passive';

                                    return $msg;
                                }
                            )
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->form([
                    // Inserire filtri per gestire input opzionali
                    TextInput::make('password')
                        ->label('Password SOAP')
                        ->password()
                        ->required(),
                ])
                ->requiresConfirmation()
        ];
    }
}
