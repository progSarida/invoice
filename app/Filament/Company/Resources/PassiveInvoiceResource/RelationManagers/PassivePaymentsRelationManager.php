<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;

use App\Enums\PiValidationStatus;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\Auth;

class PassivePaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'passivePayments';

    protected static ?string $pluralModelLabel = 'Pagamenti';

    protected static ?string $modelLabel = 'Pagamento';

    protected static ?string $title = 'Pagamenti';

    public function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                // Forms\Components\Select::make('passive_invoice_id')
                //     ->label('Fattura')
                //     ->placeholder('Seleziona una fattura...')
                //     ->relationship(name: 'passiveInvoice', titleAttribute: 'id')
                //     ->getOptionLabelFromRecordUsing(function (Model $record) {
                //         $fornitore = $record->supplier?->denomination ?? 'Fornitore sconosciuto';
                //         return "{$fornitore} - {$record->number}/{$record->invoice_date->format('d-m-Y')}";
                //     })
                //     ->required()
                //     ->disabled(fn ($get) => $get('validated'))
                //     ->searchable(['number', 'section', 'year'])
                //     ->live()
                //     ->preload()
                //     // ->optionsLimit(20)
                //     // ->autofocus()
                //     ->columnSpan(5),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->afterStateUpdated(function ($state, $component) {
                        if(str_contains($state, ',')){                                  // Se contiene una virgola
                            $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                        }
                        else {
                            $amount = $state ?? 0;
                        }
                        $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->rules(['numeric', 'min:0'])
                    ->suffix('€')
                    ->columnSpan(3),
                Forms\Components\Placeholder::make('')
                    ->content('')
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled(fn ($get) => $get('validated'))
                    ->date()
                    ->columnSpan(3),
                Forms\Components\Placeholder::make('')
                    ->content('')
                    ->columnSpan(2),
                Forms\Components\Toggle::make('validated')
                    ->label('Validato')
                    ->live()
                    ->default(false)
                    ->visible(fn($record) => $record)
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
                    ->columnSpan(2),
                Forms\Components\TextInput::make('bank')
                    ->label('Banca')
                    ->default(function ($operation, $record) {
                        if ($operation === 'create') {
                            $ownerRecord = $this->getOwnerRecord();
                            return $ownerRecord?->bank;
                        }
                        return $record?->bank;
                    })
                    ->columnSpan(4),
                Forms\Components\TextInput::make('iban')
                    ->label('IBAN')
                    ->default(function ($operation, $record) {
                        if ($operation === 'create') {
                            $ownerRecord = $this->getOwnerRecord();
                            return $ownerRecord?->iban;
                        }
                        return $record?->iban;
                    })
                    ->columnSpan(3),
                Forms\Components\Select::make('bank_account_id')->label('Conto')
                    ->relationship(
                        name: 'bankAccount',
                        modifyQueryUsing: fn (Builder $query) =>
                        $query->where('company_id',Filament::getTenant()->id)->orderBy('position', 'asc')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name} - $record->iban"
                    )
                    ->searchable()
                    ->required()
                    ->columnSpan(5)
                    ->preload(),
                // Forms\Components\Placeholder::make('')
                //     ->columnSpan(1),
                Forms\Components\DatePicker::make('registration_date')
                    ->label('Data registrazione')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->date()
                    ->columnSpan(3),
                Forms\Components\Select::make('registered_by_user_id')
                    ->label('Registrato da')
                    ->relationship('registrationUser', 'name')
                    ->disabled()
                    ->columnSpan(3),
                Forms\Components\DatePicker::make('validation_date')
                    ->label('Data validazione')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->visible(fn ($get) => $get('validated'))
                    ->columnSpan(2),
                Forms\Components\Select::make('validation_user_id')
                    ->label('Validato da')
                    ->relationship('validationUser', 'name')
                    ->disabled()
                    ->visible(fn ($get) => $get('validated'))
                    ->columnSpan(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_formatted')
                    ->label('Fattura')
                    ->getStateUsing(function ($record) {
                        $invoice = $record->passiveInvoice;
                        if (!$invoice) {
                            return 'Nessuna fattura';
                        }
                        return "{$invoice->number}/{$invoice->invoice_date->format('d-m-Y')}";
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Importo')
                    ->alignRight()
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
                    ->sortable()
                    ->visible(fn($record) => $record)
                    ->afterStateUpdated(function (Set $set, bool $state) {
                        if ($state) {
                            $set('validation_date', today());
                            $set('validation_user_id', Auth::id());
                        } else {
                            // Per "annullare" la validazione quando il toggle viene disattivato
                            $set('validation_date', null);
                            $set('validation_user_id', null);
                        }
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(function ($record) {
                        $ownerRecord = $this->getOwnerRecord();
                        $piValidation = $ownerRecord?->piValidation;

                        if (!$piValidation) { return false; }

                        return $piValidation->pi_validation_status === PiValidationStatus::OK;
                    })
                    ->modalWidth('6xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
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
