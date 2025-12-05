<?php

namespace App\Filament\Company\Resources;

use App\Enums\PiValidationStatus;
use App\Filament\Company\Resources\PiValidationResource\Pages;
use App\Filament\Company\Resources\PiValidationResource\RelationManagers;
use App\Models\PiValidation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PiValidationResource extends Resource
{
    protected static ?string $model = PiValidation::class;

    protected static ?string $navigationIcon = 'ri-hand-coin-line';

    public static ?string $pluralModelLabel = 'Validazioni fatture passive';

    public static ?string $modelLabel = 'Validazione fatture passive';

    protected static ?string $navigationGroup = 'Tabelle';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
       return $form
            ->columns(8)
            ->schema([
                Forms\Components\TextInput::make('name')->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(2),
                Forms\Components\TextInput::make('order')->label('Posizione')
                    ->required()
                    ->columnspan(1),
                Forms\Components\TextInput::make('description')->label('Descrizione')
                    ->maxLength(255)
                    ->columnspan(3),
                Forms\Components\Select::make('pi_validation_status')->label('Stato')
                    ->required()
                    ->options(PiValidationStatus::class)
                    ->columnspan(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order')->label('Posizione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pi_validation_status')->label('Stato')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
            'index' => Pages\ListPiValidations::route('/'),
            'create' => Pages\CreatePiValidation::route('/create'),
            'edit' => Pages\EditPiValidation::route('/{record}/edit'),
        ];
    }
}
