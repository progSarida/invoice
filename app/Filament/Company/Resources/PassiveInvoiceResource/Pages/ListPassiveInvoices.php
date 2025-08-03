<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use App\Services\AndxorSoapService;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\MaxWidth;

class ListPassiveInvoices extends ListRecords
{
    protected static string $resource = PassiveInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
            Actions\Action::make('passiveList')
                ->label('Scarica fatture passive')
                ->action(function (array $data) {
                    $soapService = app(AndxorSoapService::class);
                    try {
                        $response = $soapService->downloadPassive($data);
                        // $response = $soapService->downloadPassive(['password' => 'W3iDWc3Q9w.3AUgd2zpz4']);

                        if (!$response instanceof \App\Models\PassiveDownload) {
                            throw new \Exception($response->getMessage());
                        }

                        $msg = '';
                        if ($response->new_suppliers == 1) {
                            $msg .= 'Inserito ' . $response->new_suppliers . ' nuovo fornitore.<br> ';
                        } elseif ($response->new_suppliers > 1) {
                            $msg .= 'Inseriti ' . $response->new_suppliers . ' nuovi fornitori.<br> ';
                        }
                        if ($response->new_invoices == 1) {
                            $msg .= 'Scaricata ' . $response->new_invoices . ' nuova fattura passiva.';
                        } elseif ($response->new_invoices > 1) {
                            $msg .= 'Scaricate ' . $response->new_invoices . ' nuove fatture passive.';
                        }
                        if (empty($msg)) {
                            $msg = 'Nessuna nuova fattura o fornitore scaricato.';
                        }

                        Notification::make()
                            ->title('Fatture passive scaricate con successo.')
                            ->body($msg)
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
                    // TextInput::make('limit')
                    //     ->label('Numero fatture')
                ])
                ->requiresConfirmation()
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
