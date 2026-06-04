<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\RelationManagers;

use App\Enums\TransactionType;
use App\Services\CurrencyService;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use App\Enums\VatCodeType;
use App\Filament\Company\Resources\PostalExpenseResource;
use Filament\Tables\Table;
use App\Models\InvoiceItem;
use App\Models\InvoiceElement;
use App\Models\PostalExpense;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;
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
                Forms\Components\TextInput::make('description')->label('Voce visualizzata in fattura')
                    ->required()
                    ->columnSpan(8)
                    ->maxLength(255),

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
                            ->columnSpan(3),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data fine periodo')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->columnSpan(3),
                        Forms\Components\TextInput::make('quantity')->label('Quantità')
                            ->columnSpan(4)
                            ->live(onBlur: true)
                            ->required(fn(Get $get) => $get('measure_unit') || $get('unit_price'))
                            ->numeric()
                            // ->live(debounce: 500)
                            // ->debounce(3000)
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                // $unit_price = $get('unit_price');
                                // if(str_contains($unit_price, ',')){                                  // Se contiene una virgola
                                //     $unit_price = str_replace(',', '.', str_replace('.', '', $unit_price));                                          // rimuovo i punti e sostituisco la virgola
                                // }
                                // else {
                                //     $unit_price = $unit_price ?? 0;
                                // }
                                $unit_price = CurrencyService::parseNumber($get('unit_price'));
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

                                    $set('vat_amount', number_format($vatAmount, 2, ',', '.'));
                                    $set('total', number_format($total, 2, ',', '.'));
                                }
                                // else {
                                //     $set('amount', number_format(0, 2, ',', '.'));
                                //     $set('vat_amount', number_format(0, 2, ',', '.'));
                                //     $set('total', number_format(0, 2, ',', '.'));
                                // }
                            })
                            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state),
                        Forms\Components\TextInput::make('measure_unit')->label('Unità di misura')
                            ->live(onBlur: true)
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'L\'unità di misura deve essere lunga al massimo 10 caratteri (0-9, a-z, A-Z)')
                            ->rules([
                                'nullable',
                                'max:10',
                                'regex:/^[a-zA-Z0-9]{1,10}$/',
                            ])
                            ->required(fn(Get $get) => $get('quantity') || $get('unit_price'))
                            ->columnSpan(4)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('unit_price')->label('Prezzo unitario')
                            ->live(onBlur: true)
                            ->required(fn(Get $get) => $get('quantity') || $get('measure_unit'))
                            ->columnSpan(4)
                            // ->live(debounce: 500)
                            // ->debounce(3000)
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                $quantity = $get('quantity');
                                // if(str_contains($state, ',')){                                  // Se contiene una virgola
                                //     $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                // }
                                // else {
                                //     $amount = $state ?? 0;
                                // }
                                $amount = CurrencyService::parseNumber($state);
                                if($amount && $quantity){
                                    if (!is_numeric($amount) || !is_numeric($quantity)) return;
                                    // Calcolo importo in base a quantità e prezzo unitario
                                    $unit_price = $amount ?? 0;
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

                                    $set('vat_amount', number_format($vatAmount, 2, ',', '.'));
                                    $set('total', number_format($total, 2, ',', '.'));
                                }
                                // else {
                                //      $set('amount', number_format(0, 2, ',', '.'));
                                //     $set('vat_amount', number_format(0, 2, ',', '.'));
                                //     $set('total', number_format(0, 2, ',', '.'));
                                // }
                            })
                            // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state)),
                    ]),

                Forms\Components\TextInput::make('amount')->label('Importo')
                    ->required()
                    ->live(onBlur: true)
                    ->columnSpan(4)
                    ->prefix('€')
                    ->maxLength(255)
                    // ->live(debounce: 500)
                    // ->debounce(3000)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        // if (!is_numeric($state)) return;
                        // Calcolo importo IVA e totale quando amount cambia
                        // $rate = $get('vat_code_type')?->getRate() / 100 ?? 0;
                        // $rate = \App\Enums\VatCodeType::tryFrom($get('vat_code_type'))?->getRate() / 100 ?? 0;

                        $vatCode = $get('vat_code_type');
                        if (!$vatCode instanceof \App\Enums\VatCodeType) {
                            $vatCode = \App\Enums\VatCodeType::tryFrom($vatCode);
                        }
                        $rate = $vatCode?->getRate() / 100 ?? 0;
                        // if(str_contains($state, ',')){                                  // Se contiene una virgola
                        //     $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                        // }
                        // else {
                        //     $amount = $state ?? 0;
                        // }
                        $amount = CurrencyService::parseNumber($state);
                        $vatAmount = $amount * $rate;
                        $total = $amount + $vatAmount;

                        $set('amount', number_format($state, 2, ',', '.'));
                        $set('vat_amount', number_format($vatAmount, 2, ',', '.'));
                        $set('total', number_format($total, 2, ',', '.'));
                    })
                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state),
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state)),
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
                        $vatCode = $state;
                        if (!$vatCode instanceof \App\Enums\VatCodeType) {
                            $vatCode = \App\Enums\VatCodeType::tryFrom($vatCode);
                        }
                        $rate = $vatCode?->getRate() / 100 ?? 0;
                        // $rate = $state instanceof VatCodeType ?( $state->getRate() / 100) : (VatCodeType::tryFrom($state)?->getRate() / 100);
                        // $amount = $get('amount') ?? 0;
                        $amount = CurrencyService::parseNumber($get('amount'));
                        $vatAmount = $amount * $rate;
                        $total = $amount + $vatAmount;

                        $set('vat_amount', number_format($vatAmount, 2, ',', '.'));
                        $set('total', number_format($total, 2, ',', '.'));
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
                        $amount = CurrencyService::parseNumber($get('amount')) * $rate;
                        return number_format($amount, 2, ',', '.');
                    })
                    ->default(0.00),
                Forms\Components\TextInput::make('total')
                    ->label('Totale')
                    ->readOnly()
                    // ->numeric()
                    ->prefix('€')
                    ->columnSpan(8)
                    ->default(0.00)
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state),
                // Forms\Components\Toggle::make('is_with_vat')->label('Iva')
                //     ->required(),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn ($query) =>
                $query->selectRaw('invoice_items.*, COALESCE(amount, total) as display_total')
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Elemento'),
                Tables\Columns\TextColumn::make('amount')->label('Importo')
                    // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    // ->numeric()
                    ->alignRight()
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
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => $state?->getRate() . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_amount')->label('Importo IVA')
                    ->getStateUsing(function ($record) {
                        $rate = $record->vat_code_type?->getRate() / 100;
                        return $record->vat_code_type == null ? '' : $record->amount * $rate;
                    })
                    ->alignRight()
                    ->money('EUR', true, 'it_IT')
                    ->sortable(),
                //     ->summarize([
                //         Tables\Columns\Summarizers\Sum::make()
                //             ->label('')
                //             ->money('EUR', true, 'it_IT'),
                //     ]),
                Tables\Columns\TextColumn::make('display_total')
                    ->label('Totale')
                    ->money('EUR', true, 'it_IT')
                    ->alignRight()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                    ]),
                // Tables\Columns\TextColumn::make('total')->label('Totale')
                //     // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                //     // ->numeric()
                //     ->getStateUsing(function ($record) {
                //         return $record->amount ?? $record->total;
                //     })
                //     ->money('EUR', true, 'it_IT')
                //     ->sortable()
                //     ->summarize([
                //         Tables\Columns\Summarizers\Sum::make()
                //             ->label('')
                //             ->money('EUR', true, 'it_IT'),
                //             // ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                //     ]),
                // Tables\Columns\IconColumn::make('is_with_vat')->label('Iva')
                //     ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->hidden(fn() => !is_null($this->getOwnerRecord()->parent_id))
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
                    ->hidden(function() {
                        $pageClass = $this->getPageClass();
                        if (str_contains($pageClass, 'View')) {
                            return true;
                        }
                        return !is_null($this->getOwnerRecord()->parent_id) ;
                    })
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
                                    // ->live(debounce: 500)
                                    // ->debounce(3000)
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Data')
                                    ->extraInputAttributes(['class' => 'text-center'])
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
                                // $amount = ($expense->notify_amount ?? 0) + ($expense->notify_expense_amount ?? 0) + ($expense->mark_expense_amount ?? 0);
                                $amount = ($expense->notify_amount ?? 0) + ($expense->mark_expense_amount ?? 0);

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
                            $totPostalExpense = 0;
                            $expense = PostalExpense::find($expenseData['id']);

                            if ($expense) {
                                // $amount = ($expense->notify_amount ?? 0) + ($expense->notify_expense_amount ?? 0) + ($expense->mark_expense_amount ?? 0);
                                // $totPostalExpense += ($expense->notify_amount ?? 0) + ($expense->notify_expense_amount ?? 0) + ($expense->mark_expense_amount ?? 0);
                                $totPostalExpense += ($expense->notify_amount ?? 0) + ($expense->mark_expense_amount ?? 0);
                                // // Crea l'invoice item
                                // $invoiceItem = InvoiceItem::create([
                                //     'invoice_id' => $invoice->id,
                                //     // 'description' => 'Rimborso spese di notifica da ' . ($expense->supplier_id ? $expense->supplier->denomination : $expense->supplier_name),
                                //     'description' => 'Rimborsi escl.Art. 15 ex D.P.R. 633/72',
                                //     'amount' => $amount,
                                //     'total' => $amount,
                                //     'vat_code_type' => VatCodeType::VC06,
                                //     'auto' => false,
                                //     'postal_expense_id' => $expense->id
                                // ]);

                                // // $invoiceItem->invoice->updateTotal();
                                // $invoiceItem->save();
                                // $invoiceItem->checkStampDuty();
                                // $invoiceItem->autoInsert();
                                // $invoiceItem->invoice->updateTotal();

                                // // Aggiorna la spesa postale con l'ID della fattura
                                // PostalExpense::withoutEvents(function () use ($expense, $invoice) {
                                //     $expense->update([
                                //         'reinvoice_id' => $invoice->id,
                                //         'reinvoice_number' => $invoice->number,
                                //         'reinvoice_date' => $invoice->invoice_date,
                                //         'reinvoice_amount' => $invoice->total,
                                //         'reinvoice_insert_user_id' => Auth::id(),
                                //         'reinvoice_insert_date' => today()
                                //     ]);
                                // });

                                // $expense->update([
                                //     'reinvoice_id' => $invoice->id,
                                //     'reinvoice_number' => $invoice->number,
                                //     'reinvoice_date' => $invoice->invoice_date,
                                //     'reinvoice_amount' => $invoice->total,
                                //     'reinvoice_insert_user_id' => Auth::id(),
                                //     'reinvoice_insert_date' => today()
                                // ]);

                                // Crea l'invoice item
                                $invoiceItem = InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    // 'description' => 'Rimborso spese di notifica da ' . ($expense->supplier_id ? $expense->supplier->denomination : $expense->supplier_name),
                                    'description' => 'Rimborsi escl.Art. 15 ex D.P.R. 633/72',
                                    'amount' => $totPostalExpense,
                                    'total' => $totPostalExpense,
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
                                PostalExpense::withoutEvents(function () use ($expense, $invoice, $totPostalExpense) {
                                    $expense->update([
                                        'reinvoice_id' => $invoice->id,
                                        'reinvoice_number' => $invoice->number,
                                        'reinvoice_date' => $invoice->invoice_date,
                                        'reinvoice_amount' => $invoice->total + $totPostalExpense,
                                        'reinvoice_insert_user_id' => Auth::id(),
                                        'reinvoice_insert_date' => today()
                                    ]);
                                });
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
                    ->modalCancelActionLabel('Annulla'),

                Tables\Actions\Action::make('create_postal_expense')
                    ->label('Crea spesa di notifica')
                    ->hidden(function () {
                        $pageClass = $this->getPageClass();
                        if (str_contains($pageClass, 'View')) {
                            return true;
                        }
                        $contractId = $this->getOwnerRecord()->contract_id;
                        return PostalExpense::where('new_contract_id', $contractId)
                            ->where('reinvoice_id', null)
                            ->exists();
                    })
                    ->url(function () {
                        $contractId = $this->getOwnerRecord()->contract_id;
                        return PostalExpenseResource::getUrl('create', ['new_contract_id' => $contractId]);
                    })
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn($record) => $record->vat_code_type == VatCodeType::VC06A || $record->auto || $record->postal_expense_id)
                    ->using(function (InvoiceItem $record, array $data): InvoiceItem {
                        $record->fill($data);
                        $record->calculateTotal();
                        $record->save();
                        $record->checkStampDuty();
                        $record->autoInsert();
                        return $record;
                    }),
                Tables\Actions\DeleteAction::make()
                    // ->visible(fn ($record) => $record->vat_code_type !== VatCodeType::VC06A && $record->auto !== true)
                    ->visible(fn ($record) => $record->invoice_element_id || $record->postal_expense_id)
                    ->using(function (InvoiceItem $record): InvoiceItem {
                        $invoice = $record->invoice;

                        if ($record->postal_expense_id) {
                            $postalExpense = PostalExpense::find($record->postal_expense_id);
                            if ($postalExpense) {
                                if($invoice->invoice){
                                    PostalExpense::withoutEvents(function () use ($postalExpense, $invoice) {
                                        $postalExpense->update([
                                            'reinvoice_id' => $invoice->parent_id,
                                            'reinvoice_number' => $invoice->invoice->number,
                                            'reinvoice_date' => $invoice->invoice->invoice_date,
                                            'reinvoice_amount' => $invoice->invoice->total,
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
                        $invoice->invoiceCheckStampDuty();
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
