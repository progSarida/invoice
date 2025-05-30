<?php

namespace App\Filament\Resources;

use App\Enums\LiquidationType;
use App\Enums\ShareholderType;
use Filament\Forms;
use Filament\Tables;
use App\Models\Company;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Fieldset;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use App\Filament\Resources\CompanyResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CompanyResource\RelationManagers;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    public static ?string $pluralModelLabel = 'Aziende';

    public static ?string $modelLabel = 'Azienda';

    protected static ?string $navigationIcon = 'gmdi-business-center-r';

    // protected static ?string $navigationGroup = 'Parametri';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(11),
                Forms\Components\Toggle::make('is_active')->label('Attiva')
                            ->columnspan(1),
                Fieldset::make('Informazioni')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Nome')                             //
                            ->required()
                            ->maxLength(255)
                            ->columnspan(6),
                        Forms\Components\TextInput::make('vat_number')->label('Partita Iva')
                            ->required()
                            ->maxLength(255)
                            ->columnspan(3),
                        Forms\Components\TextInput::make('tax_number')->label('Codice fiscale')
                            ->required()
                            ->maxLength(255)
                            ->columnspan(3),
                        Forms\Components\TextInput::make('city_code')->label('Codice catastale')            //
                            ->required()
                            ->maxLength(4)
                            ->columnspan(2),
                        Forms\Components\TextInput::make('address')->label('Indirizzo')
                            ->maxLength(255)
                            ->columnspan(6),
                        Forms\Components\Select::make('city_code')->label('Città')
                            ->relationship(name: 'city', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->columnspan(4),
                        Forms\Components\TextInput::make('email')->label('Email')                           //
                            ->required()
                            ->maxLength(255)
                            ->columnspan(6),
                        Forms\Components\TextInput::make('phone')->label('Telefono')
                            ->required()
                            ->maxLength(255)
                            ->columnspan(3),
                        Forms\Components\TextInput::make('fax')->label('Fax')
                            ->required()
                            ->maxLength(255)
                            ->columnspan(3),
                    ])
                    ->columns(12),
                Fieldset::make('Iscrizione Albo professionale')
                    ->schema([
                        Forms\Components\TextInput::make('register')->label('Albo professionale')           //
                            ->maxLength(255)
                            ->columnspan(3),
                        Forms\Components\Select::make('register_province_id')->label('Provincia Albo')
                            ->relationship(name: 'registerProvince', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->columnspan(3),
                        Forms\Components\TextInput::make('register_number')->label('Numero')
                            ->maxLength(255)
                            ->columnspan(3),
                        Forms\Components\DatePicker::make('register_date')->label('Data iscrizione')
                                ->columnSpan(3),
                    ])
                    ->columns(12),
                Fieldset::make('Iscrizione REA')
                    ->schema([
                        Forms\Components\Select::make('rea_province_id')->label('Ufficio')
                            ->relationship(name: 'reaProvince', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->columnspan(4),
                        Placeholder::make('')
                            ->label('')
                            ->content('')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('rea_number')->label('Numero')
                            ->maxLength(255)
                            ->columnspan(2),
                        Placeholder::make('')
                            ->label('')
                            ->content('')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('nominal_capital')->label('Capitale sociale')
                            ->maxLength(255)
                            ->columnspan(2),
                        Placeholder::make('')
                            ->label('')
                            ->content('')
                            ->columnSpan(6),
                        Forms\Components\Select::make('shareholders')
                            ->label('Socio unico')
                            ->options(ShareholderType::options())
                            ->columnspan(3),
                        Forms\Components\Select::make('liquidation')
                            ->label('Stato liquidazione')
                            ->options(LiquidationType::options())
                            ->columnspan(3),
                    ])
                    ->columns(12)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vat_number')->label('Partita Iva')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')->label('Indirizzo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('Città')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefono')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')->label('Attiva'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Parametri';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }
}
