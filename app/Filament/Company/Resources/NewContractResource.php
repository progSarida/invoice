<?php

namespace App\Filament\Company\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\Company;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\AccrualType;
use App\Models\NewContract;
use Filament\Facades\Filament;
use App\Enums\TenderPaymentType;
use Filament\Resources\Resource;
use Filament\Forms\Components\View;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\NewContractResource\Pages;
use App\Filament\Company\Resources\NewContractResource\RelationManagers;
use App\Filament\Company\Resources\ContractDetailsResource\RelationManagers\ContractDetailsRelationManager;

class NewContractResource extends Resource
{
    protected static ?string $model = NewContract::class;

    public static ?string $pluralModelLabel = 'Nuovi Contratti';

    public static ?string $modelLabel = 'Contratto';

    protected static ?string $navigationIcon = 'govicon-file-contract-o';

    protected static ?string $navigationGroup = 'Gestione';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    ->hintAction(
                        Action::make('Nuovo')
                            ->icon('govicon-user-suit')
                            ->form( fn(Form $form) => ClientResource::modalForm($form) )
                            ->modalWidth('7xl')
                            ->modalHeading('')
                            ->action( fn(array $data, Client $client) => NewContractResource::saveClient($data, $client) )
                    )
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                    )
                    ->required()
                    ->searchable('denomination')
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(5),
                Forms\Components\Select::make('tax_type')
                    ->label('Entrata')
                    ->options(TaxType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(3),
                DatePicker::make('start_validity_date')
                    ->label('Inizio Validità')
                    ->date()
                    ->columnSpan(2),
                DatePicker::make('end_validity_date')
                    ->label('Fine Validità')
                    ->date()
                    ->columnSpan(2),
                Forms\Components\Select::make('accrual_type_id')
                    ->label('Competenza')
                    ->relationship('accrualType', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(3),
                Forms\Components\Select::make('payment_type')
                    ->label('Tipo pagamento')
                    ->options(TenderPaymentType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(3),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->columnSpan(3)
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->rules(['numeric', 'min:0'])
                    ->suffix('€'),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(3),
                Forms\Components\TextInput::make('office_name')
                    ->label('Nome ufficio')
                    ->required()
                    ->columnSpan(3),
                Forms\Components\TextInput::make('office_code')
                    ->label('Codice ufficio')
                    ->required()
                    ->columnSpan(3),
                View::make('links.ipa-link')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('cig_code')
                    ->label('CIG')
                    ->required()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('cup_code')
                    ->label('CUP')
                    ->required()
                    ->columnSpan(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_type')
                    ->label('Entrata')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('accrualType.name')
                    ->label('Competenza')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Tipo pagamento')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_validity_date')
                    ->label('Inizio contratto')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_validity_date')
                    ->label('Fine contratto')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . " €"),
            ])
            ->filters([
                SelectFilter::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                    )
                    ->searchable()->preload()
                    ->optionsLimit(5),
                SelectFilter::make('tax_type')->label('Entrata')->options(TaxType::class)
                    ->multiple()->preload(),
                SelectFilter::make('accrual_type_id')->label('Competenza')->relationship('accrualType', 'name'),
                SelectFilter::make('payment_type')->label('Tipo pagamento')->options(TenderPaymentType::class)
                    ->multiple()->preload(),
            ],layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(4)
            ->actions([
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_validity_date', 'asc');
    }

    public static function getRelations(): array
    {
        return [
             ContractDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewContracts::route('/'),
            'create' => Pages\CreateNewContract::route('/create'),
            'edit' => Pages\EditNewContract::route('/{record}/edit'),
        ];
    }

    public static function getNavigationSort(): ?int
    {
        return 9;
    }

    public static function saveClient(array $data, Client $client): void
    {
        $client->company_id = Filament::getTenant()->id;
        $client->type = $data['type'];
        $client->subtype = $data['subtype'];
        $client->denomination = $data['denomination'];
        $client->address = $data['address'];
        $client->city_id = $data['city_id'];
        // $client->zip_code = $data['zip_code'];
        $client->tax_code = $data['tax_code'];
        $client->vat_code = $data['vat_code'];
        $client->email = $data['email'];
        // $client->ipa_code = $data['ipa_code'];
        $client->save();
        Notification::make()
            ->title('Cliente salvato con successo')
            ->success()
            ->send();
    }
}
