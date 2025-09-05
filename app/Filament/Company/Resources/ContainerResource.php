<?php

namespace App\Filament\Company\Resources;

use App\Enums\TaxType;
use App\Enums\TenderPaymentType;
use App\Filament\Company\Resources\ContainerResource\Pages;
use App\Filament\Company\Resources\ContainerResource\RelationManagers;
use App\Filament\Company\Resources\ContainerResource\RelationManagers\ContractsRelationManager;
use App\Filament\Company\Resources\ContainerResource\RelationManagers\TendersRelationManager;
use App\Models\Container;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContainerResource extends Resource
{
    protected static ?string $model = Container::class;

    public static ?string $pluralModelLabel = 'Repertorio';

    public static ?string $modelLabel = 'Repertorio';

    protected static ?string $navigationIcon = 'phosphor-folder-simple-user-duotone';

    protected static ?string $navigationGroup = 'Archivio';

    public static function shouldRegisterNavigation(): bool{ return true; }                             // se messo a false nasconde 'Archivio'

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['client.denomination','tender.cig_code','tender.office_name','tender.office_code'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ["Cliente"=>$record->client->denomination];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return ContainerResource::getUrl('edit', ['record' => $record]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('')->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')->label('Denominazione')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('tax_types')->label('Entrate')
                    ->multiple()
                    ->options(
                        [
                            "cds" => "CDS",
                            "ici" => "ICI",
                            "imu" => "IMU",
                            "libero" => "LIBERO",
                            "park" => "PARK",
                            "pub" => "PUB",
                            "tari" => "TARI",
                            "tep" => "TEP",
                            "tosap" => "TOSAP"
                        ]
                    ),
                Forms\Components\Select::make('accrual_types')->label('Competenze')
                    ->multiple()
                    ->options(
                        [
                            "ordinary" => "Competenza ordinaria",
                            "coercive" => "Competenza coattiva",
                            "verification" => "Accertamenti",
                            "service" => "Servizi"
                        ])
                ])->columns(2),
                Section::make('')->visible()->schema([
                    Fieldset::make('Appalto')
                        ->relationship('tender')
                        ->schema([
                            // Forms\Components\Select::make('client_id')->label('Cliente')
                            //     ->relationship(name: 'client', titleAttribute: 'denomination')
                            //     ->required()
                            //     ->searchable()
                            //     ->preload(),
                            // Forms\Components\Select::make('tax_type')->label('Entrata')
                            //     ->options(TaxType::class)
                            //     ->required()
                            //     ->searchable()
                            //     ->preload(),
                            Forms\Components\Select::make('type')->label('Tipo pagamento')
                                ->options(TenderPaymentType::class)
                                ->required()
                                ->searchable()
                                ->preload(),
                            Forms\Components\TextInput::make('office_name')->label('Nome ufficio')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('office_code')->label('Codice ufficio')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\DatePicker::make('date')->label('Data'),
                            Forms\Components\TextInput::make('cig_code')->label('CIG')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('cup_code')->label('CUP')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('rdo_code')->label('RDO')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('reference_code')->label('Codice riferimento')
                                ->maxLength(255),
                        ])
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tax_types')->badge()->label('Entrate')
                     ->color(fn (string $state): string => match ($state) {
                            "CDS" => "info",
                            "ICI" => "warning",
                            "IMU" => "success",
                            "LIBERO" => "danger",
                            "PARK" => "info",
                            "PUB" => "info",
                            "TARI" => "primary",
                            "TEP" => "primary",
                            "TOSAP" => "warning"
                    })
                    ->separator(', ')
                    ->searchable(),
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
            ContractsRelationManager::class,
            // TendersRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContainers::route('/'),
            'create' => Pages\CreateContainer::route('/create'),
            'edit' => Pages\EditContainer::route('/{record}/edit'),
        ];
    }
}
