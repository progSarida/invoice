<?php

namespace App\Filament\Company\Resources\InvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    protected static ?string $pluralModelLabel = 'Voci in fattura';

    protected static ?string $modelLabel = 'Voce in fattura';

    protected static ?string $title = 'Voci in fattura';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')->label('Importo')
                    ->required()
                    ->numeric(),
                Forms\Components\Toggle::make('is_with_vat')->label('Iva')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Descrizione'),
                Tables\Columns\TextColumn::make('amount')->label('Importo')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_with_vat')->label('Iva')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
