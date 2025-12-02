<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\TransactionResource\Pages;
use App\Filament\Company\Resources\TransactionResource\RelationManagers;
use App\Models\Client;
use App\Models\Transaction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'hugeicons-money-bag-02';

    public static ?string $pluralModelLabel = 'Prima nota';

    public static ?string $modelLabel = 'Registrazione';

    protected static ?string $navigationGroup = 'Prima nota';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                DatePicker::make('date')->label('Data')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->required()
                    ->columnSpan(2),
                Select::make('instrument_id')->label('Conto')
                    ->required()
                    ->relationship(name: 'instrument', titleAttribute: 'name')
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(6),
                TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->columnSpan(12),
                Select::make('client_id')->label('Cliente')
                    // ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getSearchResultsUsing(function (string $search) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query base con le stesse condizioni del relationship
                        $query = Client::query();

                        // Cerca separatori (spazio, virgola, slash, trattino)
                        $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                        if (count($parts) >= 2) {
                            // Cerca ogni "parte" all'interno del campo denomination
                            $query->where(function ($q) use ($parts) {
                                foreach ($parts as $part) {
                                    $q->where('denomination', 'LIKE', "%{$part}%");
                                }
                            });
                        } elseif (count($parts) === 1) {
                            // Un solo valore: cerca SOLO match esatto in number o year
                            $value = $parts[0];
                            $query->where(function ($q) use ($value) {
                                $q->where('denomination', 'LIKE', "%{$value}%");
                            });
                        }

                        return $query
                            ->orderBy('denomination', 'asc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $subtype = $record->subtype->getLabel() ?? 'Cliente sconosciuto';
                                $denomination = $record->denomination ?? 'N/A';
                                $label = strtoupper("{$subtype}") . " - $denomination";

                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function (?int $value) {
                        if (!$value) {
                            return null;
                        }
                        $record = Client::find($value);

                        if (!$record) {
                            return null;
                        }

                        return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                    })
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                    )
                    ->searchable()
                    ->live()
                    ->disabled(fn(callable $get) => $get('supplier_id'))
                    ->preload()
                    ->columnSpan(6),
                Select::make('supplier_id')->label('Fornitore')
                    ->relationship(name: 'supplier', titleAttribute: 'denomination')
                    ->searchable()
                    ->live()
                    ->disabled(fn(callable $get) => $get('client_id'))
                    ->preload()
                    ->columnSpan(6),
                TextInput::make('in_amount')->label('Entrata')
                    ->columnSpan(4)
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->disabled(fn(callable $get) => !$get('client_id'))
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->debounce('500ms')
                    ->afterStateUpdated(function(callable $set, $state, $component){
                        $lastTransaction =Transaction::where('company_id', Filament::getTenant()->id)->orderBy('date', 'desc')->first();
                        $lastBalance = $lastTransaction ? $lastTransaction->progressive_balance : 0;
                        $newBalance = $lastBalance + $state;
                        $clean = preg_replace('/[^\d,\.-]/', '', $state);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                        $set('progressive_balance', number_format($newBalance, 2, ',', '.'));
                    })
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€'),
                TextInput::make('out_amount')->label('Uscita')
                    ->columnSpan(4)
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->disabled(fn(callable $get) => !$get('supplier_id'))
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->debounce('500ms')
                    ->afterStateUpdated(function(callable $set, $state, $component){
                        $lastTransaction =Transaction::where('company_id', Filament::getTenant()->id)->orderBy('date', 'desc')->first();
                        $lastBalance = $lastTransaction ? $lastTransaction->progressive_balance : 0;
                        $newBalance = $lastBalance - $state;
                        $clean = preg_replace('/[^\d,\.-]/', '', $state);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                        $set('progressive_balance', number_format($newBalance, 2, ',', '.'));
                    })
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€'),
                TextInput::make('progressive_balance')->label('Saldo progressivo')
                    ->required()
                    ->columnSpan(4)
                    ->live()
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->readOnly()
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('instrument.name')->label('Conto')
                    ->searchable(),
                TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                // TextColumn::make('client.denomination')->label('Cliente')
                //     ->searchable(),
                // TextColumn::make('supplier.denomination')->label('Fornitore')
                //     ->searchable(),
                TextColumn::make('counterpart')
                    ->label('Controparte')
                    ->formatStateUsing(function ($record) {
                        if ($record->client) { return $record->client->denomination; }
                        if ($record->supplier) { return $record->supplier->denomination; }

                        return '-';
                    })
                    ->searchable()
                    ->sortable(false),
                TextColumn::make('in_amount')->label('Entrate')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('out_amount')->label('Uscite')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('progressive_balance')->label('Saldo progressivo')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
