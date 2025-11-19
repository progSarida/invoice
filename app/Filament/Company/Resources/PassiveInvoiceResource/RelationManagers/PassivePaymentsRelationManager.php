<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

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
                Forms\Components\Select::make('passive_invoice_id')
                    ->label('Fattura')
                    ->placeholder('Seleziona una fattura...')
                    ->relationship(name: 'passiveInvoice', titleAttribute: 'id')
                    ->getOptionLabelFromRecordUsing(function (Model $record) {
                        $fornitore = $record->supplier?->denomination ?? 'Fornitore sconosciuto';
                        return "{$fornitore} - {$record->number}/{$record->invoice_date->format('d-m-Y')}";
                    })
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->searchable(['number', 'section', 'year'])
                    ->live()
                    ->preload()
                    // ->optionsLimit(20)
                    ->columnSpan(5)
                    ->autofocus(),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->rules(['numeric', 'min:0'])
                    ->suffix('€')
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->disabled(fn ($get) => $get('validated'))
                    ->date()
                    ->columnSpan(2),
                Forms\Components\Placeholder::make('')
                    ->content('')
                    ->columnSpan(1),
                Forms\Components\Toggle::make('validated')
                    ->label('Validato')
                    ->live()
                    ->default(false)
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('registration_date')
                    ->label('Data registrazione')
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
                            $record->validation_user_id = auth()->id();
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
