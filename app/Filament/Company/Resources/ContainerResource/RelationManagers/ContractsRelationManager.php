<?php

namespace App\Filament\Company\Resources\ContainerResource\RelationManagers;

use App\Enums\ContractType;
use App\Enums\TaxType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';


    protected static ?string $pluralModelLabel = 'Contratti';

    protected static ?string $modelLabel = 'Contratto';

    protected static ?string $title = 'Contratti';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Forms\Components\Select::make('client_id')->label('Cliente')
                //     ->relationship(name: 'client', titleAttribute: 'denomination')
                //     ->required()
                //     ->searchable()
                //     ->preload(),
                // Forms\Components\Select::make('tax_type')->label('Entrata')
                //     ->options(TaxType::class)
                //     ->required()
                //     ->searchable()
                //     ->preload(),
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                // Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('tax_type')->label('Entrata')
                //     ->searchable()
                //     ->badge()
                //     ->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Tipo')
                    // ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('number')->label('Numero')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_date')->label('Data contratto')
                    ->date('d F Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('validity_date')->label('Data validità')
                    ->date('d F Y')
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
