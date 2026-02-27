<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;

use App\Enums\TransactionType;
use App\Enums\VatCodeType;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
            ->columns(12)
            ->schema([
                Forms\Components\TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan('full'),
                Forms\Components\Section::make('Opzioni')
                    // ->collapsible()
                    ->columns(12)
                    ->collapsed()
                    ->label('')
                    ->schema([
                        Forms\Components\Select::make('transaction_type')
                            ->label('Tipo di transazione')
                            ->options(
                                collect(TransactionType::cases())->mapWithKeys(fn ($case) => [
                                    $case->value => $case->getLabel(),
                                ])->toArray()
                            )
                            ->columnSpan(4),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data inizio periodo')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->columnSpan(4),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data fine periodo')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->columnSpan(4),
                        Forms\Components\TextInput::make('quantity')->label('Quantità')
                            ->columnSpan(4)
                            ->live(onBlur: true)
                            ->numeric()
                            // ->live(debounce: 500)
                            ->debounce(3000),
                        Forms\Components\TextInput::make('measure_unit')->label('Unità di misura')
                            ->live(onBlur: true)
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'L\'unità di misura deve essere lunga al massimo 10 caratteri (0-9, a-z, A-Z)')
                            ->rules([
                                'nullable',
                                'max:10',
                                'regex:/^[a-zA-Z0-9]{1,10}$/',
                            ])
                            ->columnSpan(4)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('unit_price')->label('Prezzo unitario')
                            ->live(onBlur: true)
                            ->columnSpan(4)
                            // ->live(debounce: 500)
                            ->debounce(3000),
                    ]),
                Forms\Components\TextInput::make('total_price')
                    ->label('Totale')
                    ->required()
                    // ->numeric()
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->prefix('€')
                    ->columnSpan(4),
                Forms\Components\TextInput::make('vat_rate')
                    ->label('Aliquota IVA')
                    ->required()
                    // ->numeric()
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->prefix('%')
                    ->columnSpan(4),
                View::make('links.exchange-link')
                    ->columnSpan(3),
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
                    ->alignRight()
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
