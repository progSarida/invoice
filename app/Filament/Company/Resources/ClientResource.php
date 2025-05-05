<?php

namespace App\Filament\Company\Resources;

use App\Enums\ClientType;
use App\Filament\Company\Resources\ClientResource\Pages;
use App\Filament\Company\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    public static ?string $pluralModelLabel = 'Clienti';

    public static ?string $modelLabel = 'Cliente';

    protected static ?string $navigationIcon = 'govicon-user-suit';

    protected static ?string $navigationGroup = 'Gestione';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')->label('Tipo')
                    ->options(ClientType::class)
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('denomination')->label('Denominazione')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')->label('Indirizzo')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('city_id')->label('Città')
                  ->relationship(name: 'city', titleAttribute: 'name')
                  ->searchable()
                  ->preload(),
                Forms\Components\TextInput::make('zip_code')->label('Cap')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('tax_code')->label('Codice Fiscale')
                    ->maxLength(255),
                Forms\Components\TextInput::make('vat_code')->label('Partita Iva')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')->label('Email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ipa_code')->label('Codice univoco')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Tipo')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('denomination')->label('Denominazione')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')->label('Indirizzo')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')->label('Città')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('zip_code')->label('Cap')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_code')->label('Codice fiscale')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_code')->label('Partita Iva')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ipa_code')->label('Codice univoco')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Data creazione')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Data aggiornamento')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
