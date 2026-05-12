<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use App\Enums\NotifyType;
use App\Enums\ShipmentDocType;
use App\Models\SendType;
use App\Models\ShipmentType;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Storage;

class ShipmentSection
{
    public static function make(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Dati relativi al protocollo di invio e alla classificazione dell\'atto inviato in lavorazione/notifica')
            ->icon('heroicon-o-paper-airplane')
            ->collapsed(fn($record): bool => $record && $record->shipmentInserted())
            ->schema([
                self::sendProtocolNumberField(),
                self::sendProtocolDateField(),
                self::shipmentTypeField(),
                self::sendTypesField(),
                self::recipientField(),
                self::supplierField(),
                self::supplierNameField(),
                self::manageYearField(),
                self::actTypeField(),
                self::actIdField(),
                self::actYearField(),
                self::actDateField(),
                self::actAttachmentField(),
                self::shipmentInsertUserField(),
                self::shipmentInsertDateField(),
            ])
            ->columns(12);
    }

    private static function sendProtocolNumberField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('send_protocol_number')
            ->label('Numero protocollo invio')
            ->required()
            ->extraInputAttributes(['class' => 'text-right'])
            ->maxLength(255)
            ->default(function () {
                $maxProtocolNumber = \App\Models\PostalExpense::query()
                    ->selectRaw('MAX(CAST(send_protocol_number AS UNSIGNED)) as max_number')
                    ->value('max_number');
                return $maxProtocolNumber ? $maxProtocolNumber + 1 : 1;
            })
            ->columnSpan(2);
    }

    private static function sendProtocolDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('send_protocol_date')
            ->label('Data protocollo invio')
            ->extraInputAttributes(['class' => 'text-center'])
            ->required()
            ->default(now()->toDateString())
            ->columnSpan(2);
    }

    private static function shipmentTypeField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('shipment_type_id')
            ->label('Modalità di invio')
            ->required()
            ->relationship(
                name: 'shipmentType',
                titleAttribute: 'name',
                modifyQueryUsing: fn ($query, Get $get) => $query->where('notify_type', $get('notify_type'))
            )
            ->afterStateUpdated(function($state, Set $set){
                $shipmentType = ShipmentType::find($state);
                if(str_contains(strtolower($shipmentType?->name), ShipmentDocType::SPEDIZIONE->getShipmentType()))
                    $set('shipment_doc_type', ShipmentDocType::SPEDIZIONE->value);
                else if(str_contains(strtolower($shipmentType?->name), ShipmentDocType::MESSO->getShipmentType()))
                    $set('shipment_doc_type', ShipmentDocType::MESSO->value);
            })
            ->searchable()
            ->preload()
            ->live()
            ->columnSpan(4);
    }

    private static function sendTypesField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('send_types')
            ->label('Tipo di spedizione')
            ->options(function ($get) {
                $notifyType = $get('notify_type');
                if (!$notifyType) {
                    return [];
                }
                return SendType::where('notify_type', $notifyType)
                    ->pluck('name', 'id')
                    ->toArray();
            })
            ->multiple()
            ->required()
            ->searchable()
            ->formatStateUsing(function ($record) {
                if ($record) {
                    return $record->getRawOriginal('send_types')
                        ? json_decode($record->getRawOriginal('send_types'), true)
                        : [];
                }
                return [];
            })
            ->rules(['array', 'exists:send_types,id'])
            ->columnSpan(4);
    }

    private static function recipientField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('recipient')
            ->label('Destinatario notifica/trasgressore')
            ->maxLength(255)
            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
            ->columnSpanFull();
    }

    private static function supplierField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('supplier_id')
            ->label('Fornitore')
            ->relationship(
                name: 'supplier',
                titleAttribute: 'denomination',
                modifyQueryUsing: fn ($query, Get $get) => $query->where('notify_expense', true)
            )
            ->searchable()
            ->preload()
            ->live()
            ->afterStateUpdated(function (Set $set) {
                $set('passive_invoice_id', null);
            })
            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
            ->columnSpanFull();
    }

    private static function supplierNameField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('supplier_name')
            ->label('Ente da rimborsare')
            ->required()
            ->maxLength(255)
            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
            ->columnSpanFull();
    }

    private static function manageYearField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('manage_year')
            ->label('Anno di gestione')
            ->required()
            ->extraInputAttributes(['class' => 'text-right'])
            ->rules(['digits:4'])
            ->default(now()->year)
            ->columnSpan(4);
    }

    private static function actTypeField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('act_type_id')
            ->label('Tipo atto')
            ->required()
            ->relationship('actType', 'name')
            ->searchable()
            ->preload()
            ->columnSpan(4);
    }

    private static function actIdField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('act_id')
            ->label('ID atto')
            ->maxLength(255)
            ->visible(false);
    }

    private static function actYearField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('act_year')
            ->label('Anno atto')
            ->numeric()
            ->extraInputAttributes(['class' => 'text-right'])
            ->rules(['digits:4'])
            ->default(now()->year)
            ->visible(false);
    }

    private static function actDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('act_date')
            ->label('Data atto')
            ->required()
            ->extraInputAttributes(['class' => 'text-center'])
            ->columnSpan(4);
    }

    private static function actAttachmentField(): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make('act_attachment_path')
            ->label('Allegato atto')
            ->required()
            ->multiple() // AGGIUNGI QUESTA RIGA
            ->reorderable() // AGGIUNGI QUESTA RIGA
            ->directory('reg_richiesta')
            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
            ->acceptedFileTypes(['application/pdf'])
            ->hintAction(
                Forms\Components\Actions\Action::make('attach')
                    ->label('')
                    ->icon(function($record){
                        return $record?->act_attachment_path ? 'heroicon-o-x-circle' : 'heroicon-o-information-circle';
                    })
                    ->color(function($record){
                        return $record?->act_attachment_path ? 'danger' : 'gray';
                    })
                    ->tooltip(function($record){
                        return $record?->act_attachment_path ? 'Elimina allegato' : 'Caricare uno o più pdf';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Conferma eliminazione')
                    ->modalDescription('Vuoi davvero eliminare questo allegato? L\'operazione non può essere annullata.')
                    ->modalSubmitActionLabel('Sì, elimina')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function (Forms\Components\Actions\Action $action, $record) {
                        if ($record?->act_attachment_path) {
                            $disk = Storage::disk(config('filesystems.default'));
                            $disk->delete($record?->act_attachment_path);
                            $record->update(['act_attachment_path' => null]);
                        }
                    })
            )
            ->afterStateUpdated(function (Set $set, $state) {
                if (!empty($state)) {
                    $set('act_attachment_date', now()->toDateString());
                } else {
                    $set('act_attachment_date', null);
                }
            })
            ->saveUploadedFileUsing(function ($file) {
                return $file->store('reg_richiesta', config('filesystems.default'));
            })
            ->afterStateHydrated(function ($component, $state, $record) {
                // Se il record ha un path salvato (stringa) convertilo in array per il component
                if ($record && is_string($record->act_attachment_path)) {
                    $component->state([$record->act_attachment_path]);
                }
            })
            ->dehydrateStateUsing(function ($state, $record) {
                // Se lo state è vuoto o null, mantieni il valore originale del database
                if (empty($state)) {
                    return $record?->getOriginal('act_attachment_path');
                }
                return $state;
            })
            ->maxSize(10240)
            ->columnSpan(4);
    }

    private static function shipmentInsertUserField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('shipment_insert_user_id')
            ->label('Utente inserimento dati')
            ->disabled()
            ->visible(fn($record): bool => $record && $record->shipment_insert_user_id)
            ->relationship('shipmentInsertUser', 'name')
            ->searchable()
            ->preload()
            ->optionsLimit(5)
            ->columnSpan(4);
    }

    private static function shipmentInsertDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('shipment_insert_date')
            ->label('Data inserimento dati')
            ->extraInputAttributes(['class' => 'text-center'])
            ->disabled()
            ->visible(fn($record): bool => $record && $record->shipment_insert_date)
            ->columnSpan(4);
    }
}
