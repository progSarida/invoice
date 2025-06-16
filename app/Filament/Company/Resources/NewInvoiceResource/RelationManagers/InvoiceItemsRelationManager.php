<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use App\Enums\VatCodeType;
use Filament\Tables\Table;
use App\Models\InvoiceElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoice_items';

    protected static ?string $pluralModelLabel = 'Voci in fattura';

    protected static ?string $modelLabel = 'Voce in fattura';

    protected static ?string $title = 'Voci in fattura';

    public function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('invoice_element_id')
                    ->label('Elemento')
                    ->required()
                    ->live()
                    ->options(InvoiceElement::pluck('name', 'id'))
                    ->searchable()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        if ($state) {
                            $el = InvoiceElement::find($state);
                            $set('description', $el->name);
                            $set('amount', $el->amount);
                            $set('vat_code_type', $el->vat_code_type);
                        }
                    })
                    ->columnSpan(4)
                    ->preload(),
                Forms\Components\TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->columnSpan(8)
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')->label('Importo')
                    ->required()
                    ->columnSpan(4)
                    ->maxLength(255),
                Forms\Components\Select::make('vat_code_type')
                    ->label('Aliquota IVA')
                    ->required()
                    ->columnSpan(8)
                    ->options(VatCodeType::class)
                    ->searchable()
                    ->preload(),
                // Forms\Components\Toggle::make('is_with_vat')->label('Iva')
                //     ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Elemento'),
                Tables\Columns\TextColumn::make('amount')->label('Importo')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    // ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoiceElement.vat_code_type')->label('IVA')
                    // ->numeric()
                    ->formatStateUsing(fn ($state) => $state?->getRate() . '%')
                    ->sortable(),
                // Tables\Columns\IconColumn::make('is_with_vat')->label('Iva')
                //     ->boolean(),
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
