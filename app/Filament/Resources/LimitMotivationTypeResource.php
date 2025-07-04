<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Models\LimitMotivationType;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\LimitMotivationTypeResource\Pages;
use App\Filament\Resources\LimitMotivationTypeResource\RelationManagers;

class LimitMotivationTypeResource extends Resource
{
    protected static ?string $model = LimitMotivationType::class;

    protected static ?string $navigationIcon = 'tabler-clock-question';

    public static ?string $pluralModelLabel = 'Motivazioni Art. 26 633/72';

    public static ?string $modelLabel = 'Motivazioni Art. 26 633/72';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Motivazione')
                    ->maxLength(255)
                    ->required()
                    ->columnSpan(4),
                TextInput::make('description')
                    ->label('Descrizione')
                    ->maxLength(255)
                    ->columnSpan(4),
                ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')
                    ->sortable(),
                TextColumn::make('name')->label('Motivazione')
                    ->sortable(),
                // TextColumn::make('description')->label('Descrizione'),
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
            'index' => Pages\ListLimitMotivationTypes::route('/'),
            'create' => Pages\CreateLimitMotivationType::route('/create'),
            'edit' => Pages\EditLimitMotivationType::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Parametri';
    }

    public static function getNavigationSort(): ?int
    {
        return 8;
    }
}
