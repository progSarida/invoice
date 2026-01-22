<?php

namespace App\Filament\Company\Resources;

use App\Enums\PaymentType;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\PassivePayment;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\PassivePaymentResource\Pages;
use App\Filament\Company\Resources\PassivePaymentResource\RelationManagers;
use App\Models\PassiveInvoice;
use Filament\Facades\Filament;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Auth;

class PassivePaymentResource extends Resource
{
    protected static ?string $model = PassivePayment::class;

    public static ?string $pluralModelLabel = 'Pagamenti';

    public static ?string $modelLabel = 'Pagamento';

    protected static ?string $navigationIcon = 'fluentui-payment-20-o';

    protected static ?string $navigationGroup = 'Fatture passive';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            // ->disabled(function ($record): bool { return $record !== null && !Auth::user()->isManager(); })
            ->schema([
                Forms\Components\Select::make('passive_invoice_id')
                    ->label('Fattura')
                    ->placeholder('Seleziona una fattura...')
                    ->relationship(name: 'passiveInvoice', titleAttribute: 'id')
                    ->getSearchResultsUsing(function (string $search) {
                        $search = trim(preg_replace('/\s+/', ' ', $search));
                        if (empty($search)) return [];

                        $terms = explode(' ', $search);

                        return PassiveInvoice::query()
                            // Filtri fissi
                            ->whereColumn('total_payment', '<', 'total')                                        // fattura non saldata completamente
                            ->whereHas('piValidation', function ($sub) {
                                $sub->where('pi_validation_status', 'ok');                                      // fattura validata
                            })
                            // Filtri ricerca
                            ->where(function ($mainQuery) use ($terms) {
                                foreach ($terms as $term) {
                                    $mainQuery->where(function ($subQuery) use ($term) {
                                        $subQuery->whereHas('supplier', function ($s) use ($term) {
                                            $s->where('denomination', 'like', "%{$term}%");                     // ricerca su nome fornitore
                                        })
                                        ->orWhere('number', 'like', "%{$term}%")                                // ricerca su numero fattura
                                        ->orWhere('total', 'like', "%{$term}%")                                 // ricerca su totale fattura
                                        ->orWhere('invoice_date', 'like', "%{$term}%");                         // ricerca su data fattura
                                    });
                                }
                            })
                            ->with(['supplier'])
                            ->orderBy('invoice_date', 'asc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $supplierName = $record->supplier?->denomination ?? 'Fornitore sconosciuto';
                                $date = $record->invoice_date ? \Carbon\Carbon::parse($record->invoice_date)->format('d/m/Y') : 'Data N.D.';
                                $total = number_format($record->total, 2, ',', '.') . '€';
                                $number = $record->number ?? 'S.N.';

                                return [$record->id => "{$supplierName} - FT {$number} del {$date} - Tot: {$total}"];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelFromRecordUsing(function (Model $record) {
                        $fornitore = $record->supplier?->denomination ?? 'Fornitore sconosciuto';
                        return "{$fornitore} - {$record->number}/{$record->invoice_date->format('d-m-Y')} - {$record->total}";
                    })
                    ->afterStateUpdated(function ($state, Set $set) {
                        $passiveInvoice = PassiveInvoice::find($state);
                        $set('amount', $passiveInvoice?->total ?? null);
                        $set('bank', $passiveInvoice?->bank ?? null);
                        $set('iban', $passiveInvoice?->iban ?? null);
                        $set('payment_type', $passiveInvoice?->payment_type ?? null);
                    })
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->searchable()
                    ->live()
                    // ->preload()
                    // ->optionsLimit(20)
                    // ->autofocus(function ($record): bool { return $record !== null && Auth::user()->isManager(); })
                    ->columnSpan(6),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->disabled(fn ($get) => !$get('passive_invoice_id') || $get('validated'))
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->afterStateUpdated(function ($state, $component) {
                        $clean = preg_replace('/[^\d,\.-]/', '', $state);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    // ->rules(['numeric', 'min:0'])
                    ->suffix('€')
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->required()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled(fn ($get) => $get('validated'))
                    ->date()
                    ->columnSpan(2),
                // Forms\Components\Placeholder::make('')
                //     ->content('')
                //     ->columnSpan(1),
                Forms\Components\Toggle::make('validated')
                    ->label('Validato')
                    ->live()
                    ->default(false)
                    ->columnSpan(2),
                //
                Forms\Components\TextInput::make('bank')
                    ->label('Banca da accreditare')
                    ->columnSpan(8),
                Forms\Components\TextInput::make('iban')
                    ->label('IBAN da accreditare')
                    ->columnSpan(3),
                Forms\Components\Select::make('bank_account_id')->label('Conto di debito')
                    ->relationship(
                        name: 'bankAccount',
                        modifyQueryUsing: fn (Builder $query) =>
                        $query->where('company_id',Filament::getTenant()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name} - $record->iban"
                    )
                    ->searchable()
                    ->required()
                    ->columnSpan(5)
                    ->preload(),
                Forms\Components\Select::make('payment_type')
                    ->label('Metodo di pagamento')
                    ->columnSpan(5)
                    ->options(
                        collect(PaymentType::cases())
                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentType $type) => [
                                $type->getCode() => $type->getLabel()
                            ])
                            ->toArray()
                    ),
                Forms\Components\Placeholder::make('')
                    ->columnSpan(1),
                //
                Forms\Components\DatePicker::make('registration_date')
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
                Forms\Components\DatePicker::make('validation_date')
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->afterStateUpdated(function (\App\Models\ActivePayments $record, bool $state) {
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
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()->isManager()),
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
            'index' => Pages\ListPassivePayments::route('/'),
            'create' => Pages\CreatePassivePayment::route('/create'),
            'edit' => Pages\EditPassivePayment::route('/{record}/edit'),
            'view' => Pages\ViewPassivePayment::route('/{record}'),
        ];
    }
}
