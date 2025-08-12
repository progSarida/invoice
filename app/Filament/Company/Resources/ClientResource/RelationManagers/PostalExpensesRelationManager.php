<?php

namespace App\Filament\Company\Resources\ClientResource\RelationManagers;

use App\Enums\NotifyType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PostalExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'postalExpenses';

    protected static ?string $pluralModelLabel = 'Spese postali';

    protected static ?string $modelLabel = 'Spesa postale';

    protected static ?string $title = 'Spese postali';

    public function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                Forms\Components\Select::make('notify_type')
                    ->label('Tipo notifica')
                    ->required()
                    ->options(NotifyType::class)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(2)
                    ->autofocus(),
                Forms\Components\Select::make('contract_id')
                    ->label('Contratto')
                    ->relationship(
                        name: 'contract',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                    )
                    ->afterStateUpdated(function (Set $set, $state) {
                        //
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        //
                    })
                    ->columnSpan(4),
                Forms\Components\TextInput::make('send_number')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('send_number')
            ->columns([
                Tables\Columns\TextColumn::make('send_number'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->modalWidth('7xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('7xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
