<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ModelTypeResource\Pages;
use App\Filament\Company\Resources\ModelTypeResource\RelationManagers;
use App\Models\ModelType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ModelTypeResource extends Resource
{
    protected static ?string $model = ModelType::class;
    public static ?string $pluralModelLabel = 'Tipi di modelli';
    public static ?string $modelLabel = 'Tipo di modello';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 9;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(6)
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
                    ->columnspan(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order')->label('Posizione')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label('🔍 Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')->label('🔍 Descrizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Modificato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListModelTypes::route('/'),
            'create' => Pages\CreateModelType::route('/create'),
            'view' => Pages\ViewModelType::route('/{record}'),
            'edit' => Pages\EditModelType::route('/{record}/edit'),
        ];
    }
}
