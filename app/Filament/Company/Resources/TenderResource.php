<?php

namespace App\Filament\Company\Resources;

use App\Enums\TaxType;
use App\Enums\TenderPaymentType;
use App\Enums\TenderType;
use App\Filament\Company\Resources\TenderResource\Pages;
use App\Filament\Company\Resources\TenderResource\RelationManagers;
use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenderResource extends Resource
{
    protected static ?string $model = Tender::class;

    public static ?string $pluralModelLabel = 'Appalti archiviati';

    public static ?string $modelLabel = 'Appalto';

    protected static ?string $navigationIcon = 'healthicons-f-construction-worker';

    protected static ?string $navigationParentItem = 'Repertorio';

    protected static ?string $navigationGroup = 'Archivio';

    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->client->denomination;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['office_name', 'office_code', 'cig_code', 'client.denomination', 'tax_type'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            "Ufficio"=>$record->office_name ,
            "Codice ufficio"=>$record->office_code,
            "CIG"=>$record->cig_code,
            "Entrata"=>$record->tax_type->getDescription()
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return TenderResource::getUrl('edit', ['record' => $record]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('tax_type')->label('Entrata')
                    ->options(TaxType::class)
                    ->required()
                    ->searchable()
                    ->preload(),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_type')->label('Entrata')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Tipo pagamento')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('office_name')->label('Nome ufficio')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('office_code')->label('Codice ufficio')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('cig_code')->label('CIG')
                    ->sortable()
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenders::route('/'),
            'create' => Pages\CreateTender::route('/create'),
            'edit' => Pages\EditTender::route('/{record}/edit'),
        ];
    }

    public static function modalForm(Form $form){

        return $form
            ->schema([
                 Section::make('')->schema([

                    Forms\Components\Select::make('client_id')->label('Cliente')
                        ->relationship(name: 'client', titleAttribute: 'denomination')
                        ->required()
                        ->searchable()
                        ->preload()->columnSpan(1),
                    Forms\Components\Select::make('tax_type')->label('Entrata')
                        ->options(TaxType::class)
                        ->required()
                        ->searchable()
                        ->preload()->columnSpan(1),
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
                ])->columns(2)
         ]);

    }
}
