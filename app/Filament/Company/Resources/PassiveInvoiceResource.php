<?php

namespace App\Filament\Company\Resources;

use App\Enums\PaymentMode;
use App\Enums\PaymentType;
use App\Filament\Company\Resources\PassiveInvoiceResource\Pages;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\PassiveItemsRelationManager;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\PassivePaymentsRelationManager;
use App\Models\DocType;
use App\Models\PassiveInvoice;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PassiveInvoiceResource extends Resource
{
    protected static ?string $model = PassiveInvoice::class;

    public static ?string $pluralModelLabel = 'Fatture passive';

    public static ?string $modelLabel = 'Fattura passiva';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    protected static ?int $navigationSort = 3;

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Grid::make('GRID')->columnSpan(2)->schema([

                    Section::make('Riferimenti')
                        ->collapsible(false)
                        ->columns(6)
                        ->schema([

                            Forms\Components\Select::make('supplier_id')
                                ->label('Fornitore')
                                ->columnSpan(3)
                                ->relationship('supplier', 'denomination')
                                ->disabled()
                                ,

                            Forms\Components\Select::make('parent_id')
                                ->label('Fattura')
                                ->columnSpan(3)
                                ->relationship('parent', 'denomination')
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => $record->number
                                )
                                ->visible(fn (Get $get) => !is_null($get('parent_id')))
                                ->disabled()
                                ,
                        ]),

                        Section::make('Dati per il pagamento')
                            ->collapsed(false)
                            ->columns(6)
                            ->schema([
                                Forms\Components\Select::make('payment_mode')
                                    ->label('Condizioni di pagamento')
                                    ->columnSpan(2)
                                    ->options(
                                        collect(PaymentMode::cases())
                                            ->sortBy(fn (PaymentMode $type) => $type->getOrder())
                                            ->mapWithKeys(fn (PaymentMode $type) => [
                                                $type->getCode() => $type->getLabel()
                                            ])
                                            ->toArray()
                                    )
                                    ->disabled()
                                    ,
                                Forms\Components\Select::make('payment_type')
                                    ->label('Metodo di pagamento')
                                    ->columnSpan(2)
                                    ->options(
                                        collect(PaymentType::cases())
                                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                                            ->mapWithKeys(fn (PaymentType $type) => [
                                                $type->getCode() => $type->getLabel()
                                            ])
                                            ->toArray()
                                    )
                                    ->disabled()
                                    ,
                                Forms\Components\DatePicker::make('payment_deadline')
                                    ->label('Scadenza pagamento')
                                    ->columnSpan(2)
                                    ->disabled(),

                                Forms\Components\DatePicker::make('last_payment_date')
                                    ->label('Data ultimo pagamento')
                                    ->columnSpan(2)
                                    ->disabled()
                                    ,

                                Forms\Components\TextInput::make('total_payment')
                                    ->label('Totale pagato')
                                    ->columnSpan(2)
                                    // ->visible(fn (Get $get) => !is_null($get('bank')))
                                    ->disabled()
                                    ,

                                Forms\Components\TextInput::make('bank')
                                    ->label('Istituto finanziario')
                                    ->columnSpan(3)
                                    ->disabled(),
                                Forms\Components\TextInput::make('iban')
                                    ->label('IBAN')
                                    ->columnSpan(3)
                                    // ->visible(fn (Get $get) => !is_null($get('iban')))
                                    ->disabled()
                                    ,
                            ]),
                // ]),
                // Grid::make('GRID')->columnSpan(3)->schema([

                    Section::make('')
                        ->columns(12)
                        ->schema([
                            Forms\Components\Select::make('doc_type')
                                ->label('Tipo documento')
                                ->columnSpan(7)
                                ->options(function (Get $get) {
                                    $docs = DocType::select('doc_types.name', 'doc_types.description')
                                        ->get();
                                    return $docs->pluck('description', 'name')->toArray();
                                })
                                ->disabled()
                                ,
                            Forms\Components\TextInput::make('number')
                                ->label('Numero')
                                ->columnSpan(3)
                                ->disabled(),
                            Forms\Components\DatePicker::make('invoice_date')
                                ->label('Data')
                                ->columnSpan(2)
                                ->disabled()
                                ,
                        ]),
                    Section::make('Descrizione')
                        ->collapsible()
                        ->schema([
                            Forms\Components\Textarea::make('description')
                                ->label('')
                                ->columnSpanFull()
                                ->disabled()
                                ,
                        ]),
                    Section::make('Status SDI')
                            ->collapsed(false)
                            ->columns(6)
                            ->schema([
                                Forms\Components\TextInput::make('sdi_status')
                                    ->label('Status')
                                    ->columnSpan(3)
                                    ->disabled()
                                    ,
                                Forms\Components\TextInput::make('sdi_code')
                                    ->label('Codice SDI')
                                    ->columnSpan(3)
                                    ->disabled()
                                    ,
                            ]),
                // ]),
            
            // ])->columns(5);
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('docType.description')
                    ->label('Tipo documento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('supplier.denomination')
                    ->label('Fornitore')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->wrap()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Totale a doversi')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('payment_deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF')
                    ->icon('hugeicons-pdf-01')
                    ->action(function ($record) {
                        $pdfPath = $record->pdf_path;
                        if ($pdfPath && Storage::disk('public')->exists($pdfPath)) {
                            return response()->download(
                                Storage::disk('public')->path($pdfPath),
                                'document_' . $record->number . '.pdf'
                            );
                        }
                        return redirect()->back()->with('error', 'File PDF non trovato.');
                    })
                    ->visible(function ($record) {
                        $visible = !empty($record->pdf_path) && Storage::disk('public')->exists($record->pdf_path);
                        return $visible;
                    }),
                Action::make('download_xml')
                    ->label('')
                    ->tooltip('Scarica XML')
                    ->icon('hugeicons-xml-01')
                    ->action(function ($record) {
                        $xmlPath = $record->xml_path;
                        if ($xmlPath && Storage::disk('public')->exists($xmlPath)) {
                            return response()->download(
                                Storage::disk('public')->path($xmlPath),
                                'document_' . $record->number . '.xml'
                            );
                        }
                        return redirect()->back()->with('error', 'File XML non trovato.');
                    })
                    ->visible(function ($record) {
                        $visible = !empty($record->xml_path) && Storage::disk('public')->exists($record->xml_path);
                        return $visible;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()->isManagerOf(\Filament\Facades\Filament::getTenant())),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PassiveItemsRelationManager::class,
            PassivePaymentsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPassiveInvoices::route('/'),
            'create' => Pages\CreatePassiveInvoice::route('/create'),
            'edit' => Pages\EditPassiveInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('supplier');
    }
}
