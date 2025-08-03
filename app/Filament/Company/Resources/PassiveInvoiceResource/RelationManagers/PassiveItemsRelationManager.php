<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PassiveItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'passiveItems';

    protected static ?string $pluralModelLabel = 'Voci in fattura';

    protected static ?string $modelLabel = 'Voce in fattura';

    protected static ?string $title = 'Voci in fattura';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->wrap(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inizio periodo')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fine periodo')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantità')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_price')
                    ->money('EUR', true, 'it_IT')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                            // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                    ])
                    ->label('Totale'),
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
