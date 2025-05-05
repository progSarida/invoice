<?php

namespace App\Filament\Company\Resources;

use App\Enums\ContractType;
use App\Enums\TaxType;
use App\Filament\Company\Resources\ContractResource\Pages;
use App\Filament\Company\Resources\ContractResource\RelationManagers;
use App\Models\Contract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    public static ?string $pluralModelLabel = 'Contratti';

    public static ?string $modelLabel = 'Contratto';

    protected static ?string $navigationIcon = 'govicon-file-contract-o';

    protected static ?string $navigationGroup = 'Gestione';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('tax_type')->label('Tributo')
                    ->options(TaxType::class)
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('type')->label('Tipo')
                    ->options(ContractType::class)
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('number')->label('Numero')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('validity_date')->label('Data validità'),

                Forms\Components\DatePicker::make('contract_date')->label('Data contratto'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_type')->label('Tributo')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Tipo')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('number')->label('Numero')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_date')->label('Data contratto')
                    ->date()
                    ->sortable(),
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
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
