<?php

namespace App\Filament\Resources;

use App\Enums\MailProtocolType;
use App\Filament\Resources\SenderResource\Pages;
use App\Models\Sender;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SenderResource extends Resource
{
    protected static ?string $model = Sender::class;
    public static ?string $pluralModelLabel = 'Mittenti';
    public static ?string $modelLabel = 'Mittente';
    protected static ?string $navigationIcon = 'fas-user-edit';
    protected static ?string $navigationGroup = 'Parametri';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                TextInput::make('public_name')->label('Denominazione casella')->columnSpan(6)
                    ->required(),
                TextInput::make('address')->label('Indirizzo@email')->columnSpan(6)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('username', $state);
                        $set('out_username', $state);
                    }),
                Section::make('Configurazione invio')
                    ->collapsed(fn ($record) => $record && $record->address != '')
                    ->columns(12)
                    ->schema([
                        TextInput::make('out_mail_server')->label('Server')->columnSpan(6)
                            ->required(),
                        Select::make('out_mail_protocol_type')->label('Protocollo')->columnSpan(3)
                            ->required()
                            ->options(MailProtocolType::class),
                        TextInput::make('out_mail_port')->label('Porta')->columnSpan(3)
                            ->required(),
                        Checkbox::make('out_authentication')->label('Richiesta autenticazione')->columnSpan(4),
                        TextInput::make('out_username')->label('Username')->columnSpan(4)
                            ->required(),
                        TextInput::make('out_password')->label('Password')->columnSpan(4)
                            ->required()
                            ->password()
                            ->revealable()
                            ->afterStateHydrated(fn ($set, $record) => $record->out_password != '' ? $set('out_password', decrypt($record->out_password)) : $set('out_password', null)
                            )
                            ->dehydrateStateUsing(fn ($state) => $state ? encrypt($state) : ''),
                    ]),
                ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_name')
                    ->label('🔍 Nome')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('🔍 Indirizzo')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSenders::route('/'),
            'create' => Pages\CreateSender::route('/create'),
            'edit' => Pages\EditSender::route('/{record}/edit'),
        ];
    }
}
