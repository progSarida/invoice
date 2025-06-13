<?php

namespace App\Filament\Company\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Client;
use App\Models\Province;
use Filament\Forms\Form;
use App\Enums\ClientType;
use Filament\Tables\Table;
use App\Enums\ClientSubType;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\ClientResource\Pages;
use App\Filament\Company\Resources\ClientResource\RelationManagers;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    public static ?string $pluralModelLabel = 'Clienti';

    public static ?string $modelLabel = 'Cliente';

    protected static ?string $navigationIcon = 'govicon-user-suit';

    protected static ?string $navigationGroup = 'Gestione';

    protected static ?string $recordTitleAttribute = 'denomination';

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ["Tipo"=>$record->type->getLabel(), "Codice univoco"=>$record->ipa_code];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return ClientResource::getUrl('edit', ['record' => $record]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('type')->label('Tipo')
                    ->options(ClientType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $set('subtype', null);
                    })
                    ->columnspan(3),
                Forms\Components\Select::make('subtype')->label('Sottotipo')
                    ->options(function (callable $get) {
                        $type = $get('type');
                        return ClientSubType::optionsForType($type);
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnspan(3)
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        if (! $state) {
                            $set('type', null);
                            return;
                        }
                        $enum = ClientSubType::tryFrom($state);
                        if (! $enum) {
                            $set('type', null);
                            return;
                        }
                        $set('type', $enum->getType());
                    }),
                Forms\Components\TextInput::make('denomination')->label('Denominazione')
                    ->label(function (callable $get) {
                        $subtype = $get('subtype');
                        if (in_array($subtype, ['man', 'woman'])) {
                            return 'Cognome e Nome';
                        }
                        return 'Denominazione';
                    })
                    ->required()
                    ->maxLength(255)
                    ->columnspan(6),
                Forms\Components\TextInput::make('address')->label('Indirizzo')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(6),
                Forms\Components\TextInput::make('zip_code')->label('Cap')
                    ->required()
                    ->maxLength(5)
                    ->disabled()
                    ->columnspan(1),
                Forms\Components\Select::make('city_id')->label('Città')
                    ->relationship(name: 'city', titleAttribute: 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(3)
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        if ($state) {
                            $city = \App\Models\City::find($state);
                            $set('zip_code', $city?->zip_code);
                            $set('province_id', $city?->province_id);
                        } else {
                            $set('zip_code', null);
                            $set('province_id', null);
                        }
                    }),
                Forms\Components\Select::make('province_id')->label('Provincia')
                    ->options(Province::pluck('code', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->disabled()
                    ->columnSpan(2)
                    ->reactive()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record && $record->city_id) {
                            $city = \App\Models\City::find($record->city_id);
                            $component->state($city?->province_id);
                        }
                    }),
                DatePicker::make('birth_date')
                    ->label('Data di nascita')
                    ->date()
                    ->visible(fn (callable $get) => $get('type') === 'private' && ($get('subtype') === 'man' || $get('subtype') === 'woman'))
                    ->columnSpan(3),
                Forms\Components\TextInput::make('birth_place')->label('Luogo di nascita')
                    ->maxLength(255)
                    ->visible(fn (callable $get) => $get('type') === 'private' && ($get('subtype') === 'man' || $get('subtype') === 'woman'))
                    ->columnspan(3),
                Forms\Components\TextInput::make('tax_code')->label('Codice Fiscale')
                    ->maxLength(255)
                    ->columnspan(2),
                Forms\Components\TextInput::make('vat_code')->label('Partita Iva')
                    ->maxLength(255)
                    ->columnspan(2),
                Forms\Components\TextInput::make('ipa_code')
                    ->label('Codice univoco')
                    ->maxLength(255)
                    ->visible(fn (callable $get) => $get('type') === 'private')
                    ->required(fn (callable $get) => $get('type') === 'private')
                    ->columnspan(2),
                Forms\Components\Placeholder::make('birth')
                    ->label('')
                    ->content('')
                    ->visible(fn (callable $get) => $get('type') !== 'private' || ($get('subtype') !== 'man' && $get('subtype') !== 'woman'))
                    ->columnspan(6),
                Forms\Components\Placeholder::make('ipa_code')
                    ->label('')
                    ->content('')
                    ->visible(fn (callable $get) => $get('type') !== 'private')
                    ->columnspan(2),
                Forms\Components\TextInput::make('phone')->label('Tel.')
                    ->email()
                    ->maxLength(255)
                    ->columnspan(2),
                Forms\Components\TextInput::make('email')->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->columnspan(5),
                Forms\Components\TextInput::make('pec')->label('Pec')
                    ->email()
                    ->maxLength(255)
                    ->columnspan(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subtype')->label('Sottotipo')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('denomination')->label('Denominazione')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')->label('Indirizzo')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')->label('Città')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('zip_code')->label('Cap')
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
                Tables\Columns\TextColumn::make('email')->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ipa_code')->label('Codice univoco')
                    ->sortable()
                    ->searchable(),
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
                SelectFilter::make('type')->label('Tipo')->options(ClientType::class)->multiple(),
                SelectFilter::make('subtype')->label('Sottotipo')->options(ClientSubType::class)->multiple()
            ],layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(4)
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    public static function modalForm(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('type')->label('Tipo')
                    ->options(ClientType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->columnspan(3),
                Forms\Components\Select::make('subtype')->label('Sottotipo')
                    ->options(function (callable $get) {
                        $type = $get('type');
                        return ClientSubType::optionsForType($type);
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnspan(3)
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        if (! $state) {
                            $set('type', null);
                            return;
                        }
                        $enum = ClientSubType::tryFrom($state);
                        if (! $enum) {
                            $set('type', null);
                            return;
                        }
                        $set('type', $enum->getType());
                    }),
                Forms\Components\TextInput::make('denomination')->label('Denominazione')
                    ->label(function (callable $get) {
                        $subtype = $get('subtype');
                        if (in_array($subtype, ['man', 'woman'])) {
                            return 'Cognome e Nome';
                        }
                        return 'Denominazione';
                    })
                    ->required()
                    ->maxLength(255)
                    ->columnspan(6),
                Forms\Components\TextInput::make('address')->label('Indirizzo')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(9),
                Forms\Components\Select::make('city_id')->label('Città')
                    ->relationship(name: 'city', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->columnspan(3),
                Forms\Components\TextInput::make('tax_code')->label('Codice Fiscale')
                    ->maxLength(255)
                    ->columnspan(2),
                Forms\Components\TextInput::make('vat_code')->label('Partita Iva')
                    ->maxLength(255)
                    ->columnspan(2),
                Forms\Components\TextInput::make('ipa_code')
                    ->label('Codice univoco')
                    ->maxLength(255)
                    ->visible(fn (callable $get) => $get('type') === 'private')
                    ->required(fn (callable $get) => $get('type') === 'private')
                    ->columnspan(2),
                Forms\Components\Placeholder::make('ipa_code')
                    ->label('')
                    ->content('')
                    ->visible(fn (callable $get) => $get('type') !== 'private')
                    ->columnspan(2),
                Forms\Components\TextInput::make('email')->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->columnspan(6),
            ]);

    }
}
