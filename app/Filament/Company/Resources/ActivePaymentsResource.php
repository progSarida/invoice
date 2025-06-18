<?php

namespace App\Filament\Company\Resources;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Enums\TaxType;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\ActivePayments;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Filament\Company\Resources\ActivePaymentsResource\Pages;
use App\Filament\Company\Resources\ActivePaymentsResource\RelationManagers;

class ActivePaymentsResource extends Resource
{
    protected static ?string $model = ActivePayments::class;

    public static ?string $pluralModelLabel = 'Pagamenti Archiviati';

    public static ?string $modelLabel = 'Pagamento';

    protected static ?string $navigationIcon = 'polaris-payment-icon';

    protected static ?string $navigationParentItem = 'Repertorio';

    protected static ?string $navigationGroup = 'Archivio';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Fattura')
                    ->placeholder('Seleziona una fattura...')
                    ->relationship(name: 'invoice', titleAttribute: 'id')
                    ->getOptionLabelFromRecordUsing(function (Model $record) {
                        $number = str_pad($record->number, 3, '0', STR_PAD_LEFT);
                        $cliente = $record->client?->denomination ?? 'Cliente sconosciuto';
                        return "{$cliente} - {$number}/{$record->section}/{$record->year}";
                    })
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->searchable(['number', 'section', 'year'])
                    ->live()
                    ->preload()
                    // ->optionsLimit(20)
                    ->columnSpan(5),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->rules(['numeric', 'min:0'])
                    ->suffix('€')
                    ->columnSpan(2),
                DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->disabled(fn ($get) => $get('validated'))
                    ->date()
                    ->columnSpan(2),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(1),
                Toggle::make('validated')
                    ->label('Validato')
                    ->live()
                    ->default(false)
                    ->columnSpan(2),
                DatePicker::make('registration_date')
                    ->label('Data registrazione')
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
            ->query(ActivePayments::oldActivePayments())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_formatted')
                    ->label('Fattura')
                    ->getStateUsing(function ($record) {
                        $invoice = $record->invoice;
                        if (!$invoice) {
                            return 'Nessuna fattura';
                        }

                        $number = str_pad($invoice->number, 3, '0', STR_PAD_LEFT); // es: 007
                        return "{$number}/{$invoice->section}/{$invoice->year}";
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Importo')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data pagamento')
                    ->getStateUsing(function ($record) {
                        return $record->registration_date
                            ? Carbon::parse($record->registration_date)->format('d/m/Y')
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
                            $record->validation_user_id = auth()->id;
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
            'index' => Pages\ListActivePayments::route('/'),
            'create' => Pages\CreateActivePayments::route('/create'),
            'edit' => Pages\EditActivePayments::route('/{record}/edit'),
        ];
    }

    public static function getTenantOwnershipRelationshipName(): string
    {
        return 'company';
    }
}
