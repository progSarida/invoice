<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    public static ?string $pluralModelLabel = 'Aziende';

    public static ?string $modelLabel = 'Azienda';

    protected static ?string $navigationIcon = 'gmdi-business-center-r';

    protected static ?string $navigationGroup = 'Parametri';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label('Nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('vat_number')->label('Partita Iva')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')->label('Indirizzo')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('city_code')->label('Codice catastale')
                    ->required()
                    ->maxLength(4),
                Forms\Components\Toggle::make('is_active')->label('Attiva')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vat_number')->label('Partita Iva')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')->label('Indirizzo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city_code')->label('Codice catastale')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('Attiva')
                    ->boolean(),
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
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
