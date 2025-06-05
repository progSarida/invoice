<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\DocType;
use Filament\Forms\Form;
use App\Enums\ClientType;
use App\Models\Sectional;
use Filament\Tables\Table;
use App\Enums\NumerationType;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use App\Filament\Resources\SectionalResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SectionalResource\RelationManagers;

class SectionalResource extends Resource
{
    protected static ?string $model = Sectional::class;

        public static ?string $pluralModelLabel = 'Sezionali';

    public static ?string $modelLabel = 'Sezionali';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('description')
                    ->label('Descrizione')
                    ->maxLength(255)
                    ->columnSpan(2),
                Select::make('tax_regime')
                    ->label('Tipo cliente')
                    ->options(
                        collect(ClientType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                    )
                    ->columnSpan(4),
                Select::make('doc_type')
                    ->label('Tipo documento')
                    ->options(DocType::groupedOptions())
                    ->columnSpan(6),
                TextInput::make('progressive')
                    ->label('Numero progressivo')
                    ->maxLength(255)
                    ->columnSpan(2),
                Select::make('tax_regime')
                    ->label('Tipo numerazione')
                    ->options(
                        collect(NumerationType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                    )
                    ->columnSpan(4)
            ])->columns(12);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client_type')->label('Tipo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('doc_type')->label('Tipo documento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('progressive')->label('Numero successivo')
                    ->formatStateUsing(function ( Sectional $sectional) {
                        return $sectional->getNumber();
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('numeration_type')->label('Tipo numerazione')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
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
            'index' => Pages\ListSectionals::route('/'),
            'create' => Pages\CreateSectional::route('/create'),
            'edit' => Pages\EditSectional::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Parametri';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }
}
