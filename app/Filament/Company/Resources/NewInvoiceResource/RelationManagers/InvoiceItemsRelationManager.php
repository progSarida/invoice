<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\RelationManagers;

use App\Enums\TransactionType;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use App\Enums\VatCodeType;
use Filament\Tables\Table;
use App\Models\InvoiceItem;
use App\Models\InvoiceElement;
use App\Models\PostalExpense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\Auth;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

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
                            $set('description', $el->description);
                            $set('transection_type', $el->transaction_type);
                            $set('quantity', $el->quantity);
                            $set('measure_unit', $el->measure_unit);
                            $set('unit_price', $el->unit_price);
                            $set('amount', $el->amount);
                            $set('vat_code_type', $el->vat_code_type);

                            // Calcolo importo IVA e totale
                            $rate = $el->vat_code_type?->getRate() / 100 ?? 0;
                            $amount = $el->amount ?? 0;
                            $vatAmount = $amount * $rate;
                            $total = $amount + $vatAmount;

                            $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                            $set('total', number_format($total, 2, '.', ''));
                        }
                    })
                    ->columnSpan(4)
                    ->preload(),
                Forms\Components\TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->columnSpan(8)
                    ->maxLength(255),
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
                    ->columnSpan(3),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Data fine periodo')
                    ->columnSpan(3),
                Forms\Components\TextInput::make('quantity')->label('Quantità')
                    ->columnSpan(4)
                    ->numeric()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $unit_price = $get('unit_price');
                        if($state && $unit_price){
                            if (!is_numeric($state) || !is_numeric($unit_price)) return;
                            // Calcolo importo in base a quantità e prezzo unitario
                            $quantity = $state ?? 0;
                            $amount = $quantity * $unit_price;
                            $set('amount', $amount);
                            // Calcolo importo IVA e totale quando amount cambia
                            // $rate = $get('vat_code_type')?->getRate() / 100 ?? 0;
                            // $rate = \App\Enums\VatCodeType::tryFrom($get('vat_code_type'))?->getRate() / 100 ?? 0;
                            $vatCode = $get('vat_code_type');
                            if (!$vatCode instanceof \App\Enums\VatCodeType) {
                                $vatCode = \App\Enums\VatCodeType::tryFrom($vatCode);
                            }
                            $rate = $vatCode?->getRate() / 100 ?? 0;
                            $vatAmount = $amount * $rate;
                            $total = $amount + $vatAmount;

                            $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                            $set('total', number_format($total, 2, '.', ''));
                        }
                        else {
                            $set('amount', 0);
                            $set('vat_amount', 0);
                            $set('total', 0);
                        }
                    }),
                Forms\Components\TextInput::make('measure_unit')->label('Unità di misura')
                    ->columnSpan(4)
                    ->maxLength(255),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Prezzo unitario')
                    ->columnSpan(4)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $quantity = $get('quantity');
                        if($state && $quantity){
                            if (!is_numeric($state) || !is_numeric($quantity)) return;
                            // Calcolo importo in base a quantità e prezzo unitario
                            $unit_price = $state ?? 0;
                            $amount = $quantity * $unit_price;
                            $set('amount', $amount);
                            // Calcolo importo IVA e totale quando amount cambia
                            // $rate = $get('vat_code_type')?->getRate() / 100 ?? 0;
                            // $rate = \App\Enums\VatCodeType::tryFrom($get('vat_code_type'))?->getRate() / 100 ?? 0;
                            $vatCode = $get('vat_code_type');
                            if (!$vatCode instanceof \App\Enums\VatCodeType) {
                                $vatCode = \App\Enums\VatCodeType::tryFrom($vatCode);
                            }
                            $rate = $vatCode?->getRate() / 100 ?? 0;
                            $vatAmount = $amount * $rate;
                            $total = $amount + $vatAmount;

                            $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                            $set('total', number_format($total, 2, '.', ''));
                        }
                        else {
                            $set('amount', 0);
                            $set('vat_amount', 0);
                            $set('total', 0);
                        }
                    }),
                Forms\Components\TextInput::make('amount')->label('Importo')
                    ->required()
                    ->columnSpan(4)
                    ->prefix('€')
                    ->maxLength(255)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        if (!is_numeric($state)) return;
                        // Calcolo importo IVA e totale quando amount cambia
                        // $rate = $get('vat_code_type')?->getRate() / 100 ?? 0;
                        // $rate = \App\Enums\VatCodeType::tryFrom($get('vat_code_type'))?->getRate() / 100 ?? 0;
                        $vatCode = $get('vat_code_type');
                        if (!$vatCode instanceof \App\Enums\VatCodeType) {
                            $vatCode = \App\Enums\VatCodeType::tryFrom($vatCode);
                        }
                        $rate = $vatCode?->getRate() / 100 ?? 0;
                        $amount = $state ?? 0;
                        $vatAmount = $amount * $rate;
                        $total = $amount + $vatAmount;

                        $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                        $set('total', number_format($total, 2, '.', ''));
                    }),
                Forms\Components\Select::make('vat_code_type')
                    ->label('Aliquota IVA')
                    ->required()
                    ->columnSpan(8)
                    // ->options(VatCodeType::class)
                    ->options(
                        collect(VatCodeType::cases())
                            ->reject(fn ($case) => $case === VatCodeType::VC06A)
                            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                            ->toArray()
                    )
                    ->searchable()->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        // $rate = $state ? VatCodeType::tryFrom($state)?->getRate() / 100 : 0;
                        $rate = $state instanceof VatCodeType ? $state->getRate() / 100 : 0;
                        $amount = $get('amount') ?? 0;
                        $vatAmount = $amount * $rate;
                        $total = $amount + $vatAmount;

                        $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                        $set('total', number_format($total, 2, '.', ''));
                    })
                    ->preload(),
                Forms\Components\TextInput::make('vat_amount')
                    ->label('Importo IVA')
                    ->readOnly()
                    // ->numeric()
                    ->prefix('€')
                    ->columnSpan(4)
                    ->formatStateUsing(function (Get $get, Set $set) {
                        $rate = VatCodeType::tryFrom($get('vat_code_type'))?->getRate() / 100 ?? 0;
                        $amount = $get('amount') * $rate;
                        return number_format($amount, 2, '.', '');
                    })
                    ->default(0.00),
                Forms\Components\TextInput::make('total')
                    ->label('Totale')
                    ->readOnly()
                    // ->numeric()
                    ->prefix('€')
                    ->columnSpan(8)
                    ->default(0.00),
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
                    // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    // ->numeric()
                    ->money('EUR', true, 'it_IT')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                            // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                    ]),
                Tables\Columns\TextColumn::make('vat_code_type')
                    ->label('Aliquota IVA')
                    // ->numeric()
                    ->formatStateUsing(fn ($state) => $state?->getRate() . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_amount')->label('Importo IVA')
                    ->getStateUsing(function ($record) {
                        $rate = $record->vat_code_type?->getRate() / 100;
                        return $record->vat_code_type == null ? '' : $record->amount * $rate;
                    })
                    ->money('EUR', true, 'it_IT')
                    ->sortable(),
                //     ->summarize([
                //         Tables\Columns\Summarizers\Sum::make()
                //             ->label('')
                //             ->money('EUR', true, 'it_IT'),
                //     ]),
                Tables\Columns\TextColumn::make('total')->label('Totale')
                    // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    // ->numeric()
                    ->money('EUR', true, 'it_IT')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                            // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                    ]),
                // Tables\Columns\IconColumn::make('is_with_vat')->label('Iva')
                //     ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['invoice_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    })
                    ->using(function (array $data): InvoiceItem {
                        $item = InvoiceItem::create($data);
                        $item->calculateTotal();
                        $item->save();
                        $item->checkStampDuty();
                        $item->autoInsert();
                        return $item;
                    }),
                Tables\Actions\Action::make('Spese di notifica')
                    ->form([
                        Forms\Components\Repeater::make('postal_expenses')
                            ->label('Spese postali')
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Descrizione')
                                    ->disabled()
                                    ->columnSpan(6),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Importo')
                                    ->disabled()
                                    ->prefix('€')
                                    ->live(debounce: 500)
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Data')
                                    ->disabled()
                                    ->columnSpan(2),
                                Forms\Components\Checkbox::make('selected')
                                    ->label('Fattura')
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                    ])
                    ->modalWidth('5xl')
                    ->fillForm(function (): array {
                        $contractId = $this->getOwnerRecord()->contract_id;
                        $postalExpenses = PostalExpense::where('new_contract_id', $contractId)
                            ->where('reinvoice_id', null)
                            ->get();


                        return [
                            'postal_expenses' => $postalExpenses->map(function ($expense) {
                                $amount = ($expense->notify_amount ?? 0) + ($expense->notify_expense_amount ?? 0) + ($expense->mark_expense_amount ?? 0);

                                return [
                                    'id' => $expense->id,
                                    'description' => 'Spese di notifica da ' . ($expense->supplier_id ? $expense->supplier->denomination : $expense->supplier_name),
                                    // 'amount' => $expense->amount,
                                    'amount' => number_format($amount, 2, ',', '.'),
                                    'date' => $expense->created_at?->format('Y-m-d'),
                                    'selected' => true,
                                ];
                            })->toArray()
                        ];
                    })
                    ->action(function (array $data): void {
                        $selectedExpenses = collect($data['postal_expenses'])
                            ->filter(fn($expense) => $expense['selected'] === true);

                        if ($selectedExpenses->isEmpty()) {
                            // Notifica se nessun elemento è stato selezionato
                            \Filament\Notifications\Notification::make()
                                ->title('Nessuna spesa selezionata')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Crea gli invoice items per le spese selezionate
                        $invoice = $this->getOwnerRecord();
                        // dd($selectedExpenses);
                        foreach ($selectedExpenses as $expenseData) {
                            $expense = PostalExpense::find($expenseData['id']);
                            $amount = ($expense->notify_amount ?? 0) + ($expense->notify_expense_amount ?? 0) + ($expense->mark_expense_amount ?? 0);

                            if ($expense) {
                                // Crea l'invoice item
                                $invoiceItem = InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'description' => 'Spese di notifica da ' . ($expense->supplier_id ? $expense->supplier->denomination : $expense->supplier_name),
                                    'amount' => $amount,
                                    'total' => $amount,
                                    'vat_code_type' => VatCodeType::VC06,
                                    'auto' => false,
                                    'postal_expense_id' => $expense->id
                                ]);

                                // $invoiceItem->invoice->updateTotal();
                                $invoiceItem->save();
                                $invoiceItem->checkStampDuty();
                                $invoiceItem->autoInsert();
                                $invoiceItem->invoice->updateTotal();

                                // Aggiorna la spesa postale con l'ID della fattura
                                PostalExpense::withoutEvents(function () use ($expense, $invoice) {
                                    $expense->update([
                                        'reinvoice_id' => $invoice->id,
                                        'reinvoice_number' => $invoice->number,
                                        'reinvoice_date' => $invoice->invoice_date,
                                        'reinvoice_amount' => $invoice->total,
                                        'reinvoice_insert_user_id' => Auth::id(),
                                        'reinvoice_insert_date' => today()
                                    ]);
                                });

                                // $expense->update([
                                //     'reinvoice_id' => $invoice->id,
                                //     'reinvoice_number' => $invoice->number,
                                //     'reinvoice_date' => $invoice->invoice_date,
                                //     'reinvoice_amount' => $invoice->total,
                                //     'reinvoice_insert_user_id' => Auth::id(),
                                //     'reinvoice_insert_date' => today()
                                // ]);
                            }
                        }

                        // Notifica di successo
                        \Filament\Notifications\Notification::make()
                            ->title('Spese aggiunte alla fattura')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Seleziona spese di notifica')
                    ->modalDescription('Seleziona le spese postali da aggiungere alla fattura')
                    ->modalSubmitActionLabel('Aggiungi alla fattura')
                    ->modalCancelActionLabel('Annulla')
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (InvoiceItem $record, array $data): InvoiceItem {
                        $record->fill($data);
                        $record->calculateTotal();
                        $record->save();
                        $record->checkStampDuty();
                        $record->autoInsert();
                        return $record;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->vat_code_type !== VatCodeType::VC06A && $record->auto !== true)
                    ->using(function (InvoiceItem $record): InvoiceItem {
                        $invoice = $record->invoice;

                        if ($record->postal_expense_id) {
                            $postalExpense = PostalExpense::find($record->postal_expense_id);
                            if ($postalExpense) {
                                if($invoice->invoice){
                                    PostalExpense::withoutEvents(function () use ($postalExpense, $invoice) {
                                        $postalExpense->update([
                                            'reinvoice_id' => $invoice->id,
                                            'reinvoice_number' => $invoice->number,
                                            'reinvoice_date' => $invoice->invoice_date,
                                            'reinvoice_amount' => $invoice->total,
                                            'reinvoice_insert_user_id' => Auth::id(),
                                            'reinvoice_insert_date' => today()
                                        ]);
                                    });
                                }
                                else{
                                    PostalExpense::withoutEvents(function () use ($postalExpense) {                                 // Disabilita gli observer
                                        $postalExpense->update([
                                            'reinvoice_id' => null,
                                            'reinvoice_number' => null,
                                            'reinvoice_date' => null,
                                            'reinvoice_amount' => null,
                                            'reinvoice_insert_user_id' => null,
                                            'reinvoice_insert_date' => null
                                        ]);
                                    });
                                }

                            }
                        }

                        // $invoice->updateTotal();
                        $record->delete();
                        $invoice->checkStampDuty();
                        $record->autoInsert();
                        $invoice->updateTotal();

                        return $record;
                    }),
                // Tables\Actions\DeleteAction::make(),                                                                             // solo per test
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
