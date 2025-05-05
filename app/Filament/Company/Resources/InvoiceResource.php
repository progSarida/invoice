<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\InvoiceResource\Pages;
use App\Filament\Company\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static ?string $pluralModelLabel = 'Fatture';

    public static ?string $modelLabel = 'Fattura';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('company_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('client_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('tender_id')
                    ->numeric(),
                Forms\Components\TextInput::make('parent_id')
                    ->numeric(),
                Forms\Components\TextInput::make('check_validation')
                    ->maxLength(255),
                Forms\Components\TextInput::make('tax_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('invoice_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('number')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('section')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('year')
                    ->required()
                    ->numeric(),
                Forms\Components\DatePicker::make('invoice_date')
                    ->required(),
                Forms\Components\TextInput::make('budget_year')
                    ->required(),
                Forms\Components\TextInput::make('accrual_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('accrual_year')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('free_description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('vat_percentage')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('vat')
                    ->required()
                    ->numeric(),
                Forms\Components\Toggle::make('is_total_with_vat')
                    ->required(),
                Forms\Components\TextInput::make('importo')
                    ->numeric(),
                Forms\Components\TextInput::make('spese')
                    ->numeric(),
                Forms\Components\TextInput::make('rimborsi')
                    ->numeric(),
                Forms\Components\TextInput::make('ordinario')
                    ->numeric(),
                Forms\Components\TextInput::make('temporaneo')
                    ->numeric(),
                Forms\Components\TextInput::make('affissioni')
                    ->numeric(),
                Forms\Components\TextInput::make('bollo')
                    ->numeric(),
                Forms\Components\TextInput::make('total')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('no_vat_total')
                    ->numeric(),
                Forms\Components\TextInput::make('bank_account_id')
                    ->numeric(),
                Forms\Components\TextInput::make('payment_status')
                    ->required()
                    ->maxLength(255)
                    ->default('waiting'),
                Forms\Components\TextInput::make('payment_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('payment_days')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('total_payment')
                    ->numeric(),
                Forms\Components\DatePicker::make('last_payment_date'),
                Forms\Components\TextInput::make('sdi_code')
                    ->maxLength(255),
                Forms\Components\TextInput::make('sdi_status')
                    ->required()
                    ->maxLength(255)
                    ->default('da_inviare'),
                Forms\Components\DatePicker::make('sdi_date'),
                Forms\Components\TextInput::make('pdf_path')
                    ->maxLength(255),
                Forms\Components\TextInput::make('xml_path')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tender_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_validation')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tax_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('invoice_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('number')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('section')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('budget_year'),
                Tables\Columns\TextColumn::make('accrual_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('accrual_year'),
                Tables\Columns\TextColumn::make('vat_percentage')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_total_with_vat')
                    ->boolean(),
                Tables\Columns\TextColumn::make('importo')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('spese')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rimborsi')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ordinario')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('temporaneo')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('affissioni')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bollo')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('no_vat_total')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bank_account_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_days')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_payment')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_payment_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sdi_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sdi_status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sdi_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pdf_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('xml_path')
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
