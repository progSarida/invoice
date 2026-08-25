<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\RelationManagers;

use App\Enums\PaymentType;
use App\Models\ActivePayments;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ActivePaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'activePayments';

    protected static ?string $pluralModelLabel = 'Pagamenti';

    protected static ?string $modelLabel = 'Pagamento';

    protected static ?string $title = 'Pagamenti';

    public function isReadOnly(): bool
    {
        // Abilita le azioni di modifica solo quando siamo nella pagina View
        return false; // sempre abilitato (anche su Edit)
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Fattura')
                    ->placeholder('Seleziona una fattura...')
                    ->options(function () {
                        return Invoice::newInvoices()
                            ->with('client')
                            ->get()
                            ->mapWithKeys(function ($invoice) {
                                $number = str_pad($invoice->number, 3, '0', STR_PAD_LEFT);
                                $client = $invoice->client?->denomination ?? 'Cliente sconosciuto';
                                $label = "{$client} - {$number}/{$invoice->section}/{$invoice->year}";
                                return [$invoice->id => $label];
                            })
                            ->toArray();
                    })
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->searchable()
                    ->live()
                    ->preload()
                    ->default(function ($livewire) {
                        return $livewire->getOwnerRecord()->id;
                    })
                    ->columnSpan(5),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->validationMessages([
                        'required' => 'L\'importo è obbligatorio.',
                    ])
                    ->live(onBlur: true)
                    ->disabled(fn ($get) => $get('validated'))
                    // ->afterStateUpdated(function ($state, $component) {
                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                    //     }
                    //     else {
                    //         $amount = $state ?? 0;
                    //     }
                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                    //     $number = str_replace(',', '.', $clean);
                    //     $float = floatval($number);
                    //     $formatted = number_format($float, 2, ',', '.');
                    //     $component->state($formatted);
                    // })
                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->afterStateUpdated(function ($state, Get $get, $component) {
                        if (!$get('invoice_id')) return;
                        $invoice = Invoice::find($get('invoice_id'));
                        $amount = CurrencyService::parseNumber($state);
                        $formatted = number_format($amount, 2, ',', '.');
                        $component->state($formatted);
                        $newTotalPayment = $amount + $invoice->total_payment;

                        // VECCHIO CALCOLO CONTROLLO
                        // $notRound = BankAccount::find($invoice?->bank_account_id)?->name != 'Giroconto';
                        // $compare = ($invoice->client?->type?->value == 'public' && $notRound)
                        //     ? $invoice->no_vat_total
                        //     : $invoice->total;

                        // NUOVO CALCOLO CONTROLLO USANDO is_total_with_vat
                        $newNoVatTotal = $invoice->no_vat_total - $invoice->creditNotes?->sum('no_vat_total');
                        $newVat = $invoice->vat - $invoice->creditNotes?->sum('vat');
                        $temp = $newNoVatTotal- $invoice->total_payment;
                        $compare = $invoice->is_total_with_vat ? $temp + $newVat : $temp;
                        $comparePayment = $invoice->is_total_with_vat ? $newNoVatTotal + $newVat : $newNoVatTotal;

                        // if($newTotalPayment > ($compare - $invoice->total_notes)){
                        if($newTotalPayment > $comparePayment){
                            Notification::make()
                                ->title('Attenzione! Con questo inserimento il totale dei pagamenti della fattura ' . $invoice->getNewInvoiceNumber() . ' eccederebbe il dovuto.')
                                ->danger()
                                ->duration(6000)
                                ->send();
                        }
                        else if($amount != $compare){
                            Notification::make()
                                ->title("Attenzione! L'importo del pagamento è diverso dal residuo del dovuto della fattura " . $invoice->getNewInvoiceNumber())
                                ->danger()
                                ->duration(5000)
                                ->send();
                        }
                    })
                    ->default(function ($livewire) {
                        $invoice = $livewire->getOwnerRecord();
                        $newNoVatTotal = $invoice->no_vat_total - $invoice->creditNotes?->sum('no_vat_total');
                        $newVat = $invoice->vat - $invoice->creditNotes?->sum('vat');
                        $temp = $newNoVatTotal- $invoice->total_payment;
                        $amount = $invoice->is_total_with_vat ? $temp + $newVat : $temp;

                        return $amount;
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->suffix('€')
                    ->columnSpan(2),
                DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->required()
                    ->validationMessages([
                        'required' => 'La data è obbligatoria.',
                    ])
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        if (!$state) {
                            return;
                        }

                        $invoice = Invoice::find($get('invoice_id'));

                        $currentMonth = now()->month;
                        $date = \Carbon\Carbon::parse($state)->startOfDay();
                        $selectedYear = $date->year;
                        $currentYear = now()->year;

                        if ($currentMonth !== 1 && $date->year !== $currentYear) {
                            $date = $date->copy()->setYear($currentYear);       // i controlli seguenti usano la data corretta
                            $set('payment_date', $date->format('Y-m-d'));

                            Notification::make()
                                ->title('Anno corretto automaticamente')
                                ->body("Hai inserito una data del {$selectedYear}, ma l'anno corrente è il {$currentYear}.")
                                ->warning()
                                ->send();
                        }

                        // Confronto fra oggetti Carbon: con una stringa da una parte e un Carbon
                        // dall'altra PHP considera sempre maggiore l'oggetto, senza guardare la data
                        $paymentDate = $date;

                        if ($invoice && $paymentDate->lt($invoice->invoice_date->copy()->startOfDay())) {
                            Notification::make('date')
                                ->title('Attenzione! La data del pagamento è inferiore alla data della fattura.')
                                ->danger()
                                ->duration(6000)
                                // ->persistent()
                                ->send();
                        }

                        if ($paymentDate->gt(today())) {
                            Notification::make('today')
                                ->title('Attenzione! La data del pagamento è successiva alla data di oggi.')
                                ->warning()
                                ->duration(6000)
                                // ->persistent()
                                ->send();
                        }
                    })
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled(fn ($get) => $get('validated'))
                    ->date()
                    ->columnSpan(2),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(1),
                Toggle::make('validated')
                    ->label('Validato')
                    ->live()
                    ->visible(fn ($record) => $record?->amount !== null)
                    ->afterStateUpdated(function (Set $set, bool $state) {
                        if ($state) {
                            $set('validation_date', now()->format('Y-m-d'));
                            $set('validation_user_id', Auth::id());
                        } else {
                            // Per "annullare" la validazione quando il toggle viene disattivato
                            $set('validation_date', null);
                            $set('validation_user_id', null);
                        }
                    })
                    ->default(false)
                    ->columnSpan(2),
                Select::make('bank_account_id')
                    ->label('Conto')
                    ->options(function () {
                        return BankAccount::where('company_id', Filament::getTenant()->id)
                            ->orderBy('position', 'asc')
                            ->get()
                            ->mapWithKeys(function ($record) {
                                return [$record->id => "{$record->name} - {$record->iban}"];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->required()
                    ->validationMessages([
                        'required' => 'Il conto è obbligatorio.',
                    ])
                    ->default(function ($livewire) {
                        return $livewire->getOwnerRecord()->bank_account_id;
                    })
                    ->columnSpan(6)
                    ->preload(),
                Select::make('payment_type')
                    ->label('Metodo di pagamento')
                    ->options(
                        collect(PaymentType::cases())
                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentType $type) => [
                                $type->value => $type->getLabel()
                            ])
                            ->toArray()
                    )
                    ->default(function ($livewire) {                                    // metodo di pagamento ereditato dalla fattura
                        return $livewire->getOwnerRecord()->payment_type?->value;
                    })
                    ->disabled(fn ($get) => $get('validated'))
                    ->columnSpan(6),
                Forms\Components\Textarea::make('description')->label('Descrizione')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('note')->label('Note')
                    ->columnSpanFull(),
                Section::make('Dati registrazione/validazione')
                        // ->collapsible()
                        ->columns(12)
                        ->collapsed()
                        ->label('')
                        ->visible(fn ($get) => !is_null($get('registration_date')))
                        ->schema([
                            DatePicker::make('registration_date')
                                ->label('Data registrazione')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->disabled()
                                ->date()
                                ->columnSpan(2),
                            Forms\Components\Select::make('registered_by_user_id')
                                ->label('Registrato da')
                                ->relationship('registrationUser', 'name')
                                ->disabled()
                                ->columnSpan(3),
                            DatePicker::make('validation_date')
                                ->label('Data validazione')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->disabled()
                                ->visible(fn ($get) => $get('validated'))
                                ->columnSpan(2),
                            Forms\Components\Select::make('validated_by_user_id')
                                ->label('Validato da')
                                ->relationship('validationUser', 'name')
                                ->disabled()
                                ->visible(fn ($get) => $get('validated'))
                                ->columnSpan(3),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount')->label('Importo')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data pagamento')
                    ->alignCenter()
                    ->getStateUsing(function ($record) {
                        return $record->payment_date
                            ? Carbon::parse($record->payment_date)->format('d/m/Y')
                            : 'Nessuna data';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Data registrazione')
                    ->alignCenter()
                    ->getStateUsing(function ($record) {
                        return $record->registration_date
                            ? Carbon::parse($record->registration_date)->format('d/m/Y')
                            : 'Nessuna data';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('registrationUser.name')
                    ->label('Registrato da')
                    ->getStateUsing(fn ($record) => optional($record->registrationUser)->name ?? 'Nessun utente')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('validated')
                    ->label('Validato')
                    ->disabled(fn ($record) => !Auth::user()?->can('update', $record))
                    ->sortable()->afterStateUpdated(function (\App\Models\ActivePayments $record, bool $state) {
                        if ($state) {
                            $record->validation_date = now();
                            $record->validation_user_id = Auth::id();
                        } else {
                            // Se vuoi "annullare" la validazione quando il toggle viene disattivato
                            $record->validation_date = null;
                            $record->validation_user_id = null;
                        }

                        $record->save();
                    }),
                Tables\Columns\TextColumn::make('validation_date')
                    ->label('Data validazione')
                    ->getStateUsing(function ($record) {
                        return $record->validation_date
                            ? Carbon::parse($record->validation_date)->format('d/m/Y')
                            : '';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('validationUser.name')
                    ->label('Validato da')
                    ->getStateUsing(fn ($record) => optional($record->validationUser)->name ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Crea nuovo pagamento')
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()?->docType?->name !== 'TD00'
                        && $livewire->getOwnerRecord()?->docType?->name !== 'TD04'
                        && !$livewire->getOwnerRecord()?->isPaid() 
                        && Auth::user()?->can('create', ActivePayments::class))
                    ->after(fn () => $this->dispatch('refreshEditPage'))
                    ->modalWidth('6xl'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Visualizza pagamento')
                    ->visible(fn ($record) => $record->validated || !Auth::user()?->can('update', ActivePayments::class))
                    ->modalWidth('6xl'),
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica pagamento')
                    ->visible(fn ($record) => !$record->validated && Auth::user()?->can('update', ActivePayments::class))
                    ->modalWidth('6xl'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => !$record->validated && Auth::user()?->can('delete', ActivePayments::class)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
