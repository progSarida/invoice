<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
// use App\Enums\DocType;
use App\Models\Company;
use App\Models\DocType;
use Filament\Forms\Form;
use App\Enums\ClientType;
use App\Models\Sectional;
use Filament\Tables\Table;
use App\Enums\NumerationType;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use App\Filament\Resources\SectionalResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SectionalResource\RelationManagers;

class SectionalResource extends Resource
{
    protected static ?string $model = Sectional::class;

        public static ?string $pluralModelLabel = 'Sezionali';

    public static ?string $modelLabel = 'Sezionali';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('company_id')
                    ->label('Azienda')
                    ->required()
                    ->options(Company::all()->pluck('name', 'id'))
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('doc_type_id', null);
                    })
                    ->columnSpan(3),
                TextInput::make('description')
                    ->label('Descrizione')
                    ->maxLength(255)
                    ->columnSpan(1)
                    ->extraAttributes(['class' => 'w-1/2 text-center']),
                Select::make('client_type')
                    ->label('Tipo cliente')
                    ->options(
                        collect(ClientType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                    )
                    ->columnSpan(3),
                TextInput::make('progressive')
                    ->label('Numero progressivo')
                    ->maxLength(255)
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'w-1/2 text-center']),
                Select::make('numeration_type')
                    ->label('Tipo numerazione')
                    ->options(
                        collect(NumerationType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                    )
                    ->columnSpan(3),
                Placeholder::make('doc_type_placeholder')
                    ->label('')
                    ->content('Seleziona un’azienda per vedere i documenti disponibili')
                    ->visible(function (callable $get) {
                        return empty($get('company_id'));
                    })
                    ->columnSpan(12),
                Forms\Components\CheckboxList::make('doc_type_ids')
                    ->label('Tipi documento')
                    ->options(function (callable $get) {
                        $companyId = $get('company_id');
                        if ($companyId) {
                            return DocType::groupedOptions($companyId);
                        }
                        return [];
                    })
                    ->relationship(
                        name: 'docTypes',
                        titleAttribute: null,
                        modifyQueryUsing: function ($query, callable $get) {
                            $companyId = $get('company_id');
                            if ($companyId) {
                                $query->whereIn('doc_types.id', function ($subQuery) use ($companyId) {
                                    $subQuery->select('doc_type_id')
                                        ->from('company_docs')
                                        ->where('company_id', $companyId);
                                })->orderBy('doc_types.name', 'asc');
                            }
                        }
                    )
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $groupName = $record->docGroup ? $record->docGroup->name : 'Senza gruppo';
                        return "{$record->name} - {$record->description} ({$groupName})";
                    })
                    ->visible(function (callable $get) {
                        return !empty($get('company_id'));
                    })
                    ->required()
                    ->columnSpan(6),

            ])->columns(12);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client_type')->label('Tipo')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('docType')
                //     ->label('Tipo documento')
                //     ->formatStateUsing(fn($state, $record) =>
                //         $record->docType ? "{$record->docType->name} - {$record->docType->description}" : '-'
                //     )
                //     ->searchable(),
                Tables\Columns\TextColumn::make('docTypes')
                    ->label('Tipi documento')
                    ->formatStateUsing(fn($state, $record) =>
                        $record->docTypes->pluck('name')->implode(', ') ?: '-'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('progressive')->label('Numero successivo')
                    ->formatStateUsing(function ( Sectional $sectional) {
                        return $sectional->getNumber();
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('numeration_type')->label('Tipo numerazione')
                    ->searchable(),
            ])
            ->filters([
                // SelectFilter::make('client_type')->label('Tipo')->options(ClientType::class)->multiple(),
                // SelectFilter::make('doc_type')->label('Tipo documento')->options(DocType::class)->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
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
            'index' => Pages\ListSectionals::route('/'),
            'create' => Pages\CreateSectional::route('/create'),
            'edit' => Pages\EditSectional::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Parametri';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }
}
