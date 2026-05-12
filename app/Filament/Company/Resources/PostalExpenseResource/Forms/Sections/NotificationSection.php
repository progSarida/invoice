<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use App\Enums\Month;
use App\Enums\NotifyType;
use App\Services\CurrencyService;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Storage;

class NotificationSection
{
    public static function make(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Dati relativi alla lavorazione/notifica richiesta ed effettuata dal fornitore incaricato')
            ->icon('heroicon-o-bell-alert')
            ->collapsed(fn($record): bool => $record && $record->notificationInserted())
            ->visible(fn($record): bool => $record && ($record->shipment_insert_user_id && $record->shipment_insert_date))
            ->schema([
                self::orderRifField(),
                self::receiveProtocolNumberField(),
                self::receiveProtocolDateField(),
                self::workPlaceholderField(),
                self::notifyYearField(),
                self::notifyMonthField(),
                self::notifyAmountField(),
                self::amountRegistrationDateField(),
                self::notifyDateField(),
                self::notifyAttachmentField(),
                self::notifyAttachmentDateField(),
                self::notifyInsertUserField(),
                self::notifyInsertDateField(),
            ])
            ->columns(3);
    }

    private static function orderRifField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('order_rif')
            ->label('Riferimento')
            ->hintIcon('heroicon-o-information-circle', tooltip: 'Flusso, identificativo stampatore, note aggiuntive')
            ->maxLength(255);
    }

    private static function receiveProtocolNumberField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('receive_protocol_number')
            ->label('Numero protocollo ricezione')
            ->required()
            ->maxLength(255)
            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value);
    }

    private static function receiveProtocolDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('receive_protocol_date')
            ->label('Data protocollo ricezione')
            ->extraInputAttributes(['class' => 'text-center'])
            ->required()
            ->live()
            ->debounce(500)
            ->afterStateUpdated(function (Set $set, $state) {
                if ($state) {
                    $date = \Carbon\Carbon::parse($state);
                    $set('notify_year', $date->year);
                    $set('notify_month', $date->month);
                }
            });
    }

    private static function workPlaceholderField(): Placeholder
    {
        return Placeholder::make('work')
            ->label('')
            ->visible(fn(Get $get): bool => $get('notify_type') !== NotifyType::MESSO->value);
    }

    private static function notifyYearField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('notify_year')
            ->label('Anno ricezione')
            ->extraInputAttributes(['class' => 'text-right'])
            ->rules(['digits:4'])
            ->default(now()->year)
            ->disabled()
            ->visible(false)
            ->dehydrated();
    }

    private static function notifyMonthField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('notify_month')
            ->label('Mese ricezione')
            ->options(Month::class)
            ->searchable()
            ->preload()
            ->disabled()
            ->visible(false)
            ->dehydrated(true);
    }

    private static function notifyAmountField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('notify_amount')
            ->label('Importo notifica')
            ->required()
            ->inputMode('decimal')
            ->step(0.01)
            ->suffix('€')
            ->live(onBlur: true)
            ->extraInputAttributes(['class' => 'text-right'])
            ->afterStateUpdated(function (Set $set, $state, $component) {
                if ($state) {
                    $set('amount_registration_date', now()->toDateString());
                }
                $float = CurrencyService::parseNumber($state);
                $formatted = number_format($float, 2, ',', '.');
                $component->state($formatted);
            })
            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
            ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state));
    }

    private static function amountRegistrationDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('amount_registration_date')
            ->label('Data registrazione importo')
            ->extraInputAttributes(['class' => 'text-center'])
            ->required()
            ->disabled()
            ->visible(false)
            ->dehydrated();
    }

    private static function notifyDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('notify_date')
            ->label('Data notifica')
            ->required()
            ->extraInputAttributes(['class' => 'text-center']);
    }

    private static function notifyAttachmentField(): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make('notify_attachment_path')
            ->label('Allegato notifica')
            ->required()
            ->multiple()
            ->reorderable()
            ->directory('reg_post_richiesta')
            ->acceptedFileTypes(['application/pdf'])
            ->hintAction(
                Forms\Components\Actions\Action::make('attach')
                    ->label('')
                    ->icon(function($record){
                        return $record?->notify_attachment_path ? 'heroicon-o-x-circle' : 'heroicon-o-information-circle';
                    })
                    ->color(function($record){
                        return $record?->notify_attachment_path ? 'danger' : 'gray';
                    })
                    ->tooltip(function($record){
                        return $record?->notify_attachment_path ? 'Elimina allegato' : 'Caricare uno o più pdf';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Conferma eliminazione')
                    ->modalDescription('Vuoi davvero eliminare questo allegato? L\'operazione non può essere annullata.')
                    ->modalSubmitActionLabel('Sì, elimina')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function (Forms\Components\Actions\Action $action, $record) {
                        if ($record?->notify_attachment_path) {
                            $disk = Storage::disk(config('filesystems.default'));
                            $disk->delete($record?->notify_attachment_path);
                            $record->update(['notify_attachment_path' => null]);
                        }
                    })
            )
            ->maxSize(10240)
            ->preserveFilenames()
            ->saveUploadedFileUsing(function ($file) {
                return $file->store('reg_post_richiesta', config('filesystems.default'));
            })
            ->afterStateHydrated(function ($component, $state, $record) {
                // Se il record ha un path salvato (stringa) convertilo in array per il component
                if ($record && is_string($record->notify_attachment_path)) {
                    $component->state([$record->notify_attachment_path]);
                }
            })
            ->dehydrateStateUsing(function ($state, $record) {
                // Se lo state è vuoto o null, mantieni il valore originale del database
                if (empty($state)) {
                    return $record?->getOriginal('notify_attachment_path');
                }
                return $state;
            });
    }

    private static function notifyAttachmentDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('notify_attachment_date')
            ->label('Data caricamento notifica')
            ->extraInputAttributes(['class' => 'text-center'])
            ->disabled()
            ->visible(false)
            ->dehydrated();
    }

    private static function notifyInsertUserField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('notify_insert_user_id')
            ->label('Utente inserimento notifica')
            ->disabled()
            ->visible(fn($record): bool => $record && $record->notify_insert_user_id)
            ->relationship('notifyInsertUser', 'name')
            ->searchable()
            ->preload()
            ->optionsLimit(5);
    }

    private static function notifyInsertDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('notify_insert_date')
            ->label('Data inserimento notifica')
            ->extraInputAttributes(['class' => 'text-center'])
            ->disabled()
            ->visible(fn($record): bool => $record && $record->notify_insert_date);
    }
}
