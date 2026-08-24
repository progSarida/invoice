<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\Client;
use App\Models\Sectional;
use App\Models\SocialContribution;
use App\Models\Withholding;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

class OptionsSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Opzioni')
        // ->collapsible()
        ->columns(12)
        ->collapsed()
        ->label('')
        ->schema([
            Toggle::make('art_73')
                ->label('Art. 73')
                ->dehydrated()
                ->columnSpan(2)
                ->reactive()
                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                    if ($state) {
                        $set('sectional_id', null);
                        $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                        $set('number', $number);
                        NewInvoiceResource::invoiceNumber($get, $set);
                    }
                    else{
                        $clientId = $get('client_id');
                        if ($clientId) {
                            $client = Client::find($clientId);
                            if ($client && $client->type) {
                                $sectional = Sectional::where('company_id', Filament::getTenant()->id)
                                    ->where('client_type', $client->type->value)
                                    ->first();
                                if ($sectional) {
                                    $set('sectional_id', $sectional->id);
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);
                                    NewInvoiceResource::invoiceNumber($get, $set);
                                } else {
                                    $set('sectional_id', null);
                                    $set('number', null);
                                    NewInvoiceResource::invoiceNumber($get, $set);
                                    Notification::make()
                                        ->title('Nessun sezionario trovato per il tipo di cliente selezionato.')
                                        ->warning()
                                        ->send();
                                }
                            }
                        }
                    }
                }),

            Forms\Components\Select::make('social_contributions')
                ->label('')
                // ->columnSpan(4)
                ->columnSpan(6)
                ->placeholder('Cassa previdenziale')
                ->multiple()
                ->options(function () {
                    return SocialContribution::where('company_id', Filament::getTenant()->id)
                        ->get()
                        ->mapWithKeys(fn ($item) => [$item->id => $item->fund->getLabel()])
                        ->toArray();
                })
                // ->dehydrated(fn ($state) => is_array($state) && count($state)),
                ->dehydrated(),

            Forms\Components\Select::make('withholdings')
                ->label('')
                // ->columnSpan(3)
                ->columnSpan(4)
                ->placeholder('Ritenute')
                ->multiple()
                ->options(function () {
                    return Withholding::where('company_id', Filament::getTenant()->id)
                        ->get()
                        ->mapWithKeys(fn ($item) => [$item->id => $item->withholding_type->getLabel()])
                        ->toArray();
                })
                // ->dehydrated(fn ($state) => is_array($state) && count($state)),
                ->dehydrated(),

            ]);
    }
}
