<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SupplierResource\Pages;
use App\Filament\Company\Resources\SupplierResource\RelationManagers;
use App\Models\Province;
use App\Models\State;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    public static ?string $pluralModelLabel = 'Fornitori';

    public static ?string $modelLabel = 'Fornitore';

    protected static ?string $navigationIcon = 'healthicons-f-construction-worker';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                TextInput::make('denomination')
                    ->label('Denominazione')
                    ->columnSpan(3),
                TextInput::make('tax_code')
                    ->label('Codice fiscale')
                    ->columnSpan(1),
                TextInput::make('vat_code')
                    ->label('Partita IVA')
                    ->columnSpan(1),
                TextInput::make('address')
                    ->label('Indirizzo')
                    ->columnSpan(3),
                TextInput::make('civic_number')
                    ->label('Civico')
                    ->columnSpan(1),
                TextInput::make('city')
                    ->label('Città')
                    ->columnSpan(1),
                TextInput::make('zip_code')
                    ->label('CAP')
                    ->columnSpan(1),
                Placeholder::make('')
                    ->columnSpan(2),
                Select::make('province')
                    ->label('Provincia')
                    ->options(function () {
                        return Province::pluck('name', 'code')->toArray();
                    })
                    ->searchable()
                    ->reactive()
                    ->columnSpan(2),
                Select::make('country')
                    ->label('Paese')
                    ->options(function () {
                        return State::pluck('name', 'alpha2')->toArray();
                    })
                    ->searchable()
                    ->reactive()
                    ->columnSpan(2),
                Select::make('rea_office')
                    ->label('Ufficio REA')
                    ->visible(fn (Get $get) => !is_null($get('rea_office')))
                    ->options(function () {
                        return Province::pluck('name', 'code')->toArray();
                    })
                    ->searchable()
                    ->reactive()
                    ->columnSpan(1),
                Placeholder::make('')
                    ->columnSpan(1)
                    ->visible(fn (Get $get) => is_null($get('rea_office'))),
                TextInput::make('rea_number')
                    ->label('Numero REA')
                    ->visible(fn (Get $get) => !is_null($get('rea_number')))
                    ->columnSpan(1),
                Placeholder::make('')
                    ->columnSpan(1)
                    ->visible(fn (Get $get) => is_null($get('rea_number'))),
                TextInput::make('capital')
                    ->label('Capitale sociale')
                    ->visible(fn (Get $get) => !is_null($get('capital')))
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->suffix('€')
                    ->columnSpan(1),
                Placeholder::make('')
                    ->columnSpan(1)
                    ->visible(fn (Get $get) => is_null($get('capital'))),
                TextInput::make('sole_share')
                    ->label('Socio unico')
                    ->visible(fn (Get $get) => !is_null($get('sole_share')) && $get('sole_share') !== '')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'SU' => 'Socio unico',
                            'SM' => 'Più soci',
                            '' => '',
                            null => '',
                            default => 'N/D',
                        };
                    })
                    ->columnSpan(1),
                Placeholder::make('')
                    ->columnSpan(1)
                    ->visible(fn (Get $get) => is_null($get('sole_share')) || $get('sole_share') === ''),
                TextInput::make('liquidation_status')
                    ->label('Stato liquidazione')
                    ->visible(fn (Get $get) => !is_null($get('liquidation_status')) && $get('liquidation_status') !== '')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'LN' => 'Non in liquidazione',
                            'LS' => 'In liquidazione',
                            null => '',
                            default => '',
                        };
                    })
                    ->columnSpan(1),
                Placeholder::make('')
                    ->columnSpan(1)
                    ->visible(fn (Get $get) => is_null($get('liquidation_status')) || $get('liquidation_status') === ''),
                Placeholder::make('')
                    ->columnSpan(1),
                TextInput::make('phone')
                    ->label('Telefono')
                    ->columnSpan(1),
                TextInput::make('fax')
                    ->label('Fax')
                    ->columnSpan(1),
                TextInput::make('email')
                    ->label('Email')
                    ->columnSpan(3),
                Placeholder::make('')
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('denomination')->label('Denominazione')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_address')
                    ->label('Indirizzo Completo')
                    ->toggleable() // Rimosso isToggledHiddenByDefault per renderla visibile
                    ->getStateUsing(function ($record) {
                        $parts = array_filter([
                            $record->address,
                            $record->civic_number,
                            $record->city,
                        ]);
                        return !empty($parts) ? implode(' ', $parts) : 'N/D';
                    }),
                TextColumn::make('zip_code')
                    ->label('Cap')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_code')->label('Codice fiscale')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_code')->label('Partita Iva')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefono')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Data creazione')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Data aggiornamento')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
