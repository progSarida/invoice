<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManageTypeResource\Pages;
use App\Filament\Resources\ManageTypeResource\RelationManagers;
use App\Models\ManageType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ManageTypeResource extends Resource
{
    protected static ?string $model = ManageType::class;

    public static ?string $pluralModelLabel = 'Gestioni';

    public static ?string $modelLabel = 'Gestioni';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                    ->columnspan(3),
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
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListManageTypes::route('/'),
            'create' => Pages\CreateManageType::route('/create'),
            'edit' => Pages\EditManageType::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Parametri';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }
}
