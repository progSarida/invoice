<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\RelationManagers;

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
                    ->afterStateUpdated(function ($state, $component) {
                        $float = CurrencyService::parseNumber($state);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->suffix('€')
                    ->columnSpan(2),
                DatePicker::make('payment_date')
                    ->label('Data pagamento')
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
                    ->default(function ($livewire) {
                        return $livewire->getOwnerRecord()->bank_account_id;
                    })
                    ->columnSpan(5)
                    ->preload(),
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
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data pagamento')
                    ->getStateUsing(function ($record) {
                        return $record->payment_date
                            ? Carbon::parse($record->payment_date)->format('d/m/Y')
                            : 'Nessuna data';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Data registrazione')
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
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()?->docType?->name !== 'TD00' && $livewire->getOwnerRecord()?->docType?->name !== 'TD04')
                    ->after(fn () => $this->dispatch('refreshEditPage'))
                    ->modalWidth('6xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica pagamento')
                    ->modalWidth('6xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
