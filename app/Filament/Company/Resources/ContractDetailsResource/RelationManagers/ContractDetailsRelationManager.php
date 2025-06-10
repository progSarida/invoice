<?php

namespace App\Filament\Company\Resources\ContractDetailsResource\RelationManagers;

use App\Enums\ContractType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;

class ContractDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'contractDetails';

    protected static ?string $title = 'Storico contratto';

    public function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                TextInput::make('number')
                    ->label('Numero contratto')
                    ->required()
                    ->columnSpan(2),
                Forms\Components\Select::make('contract_type')
                    ->label('Tipo contratto')
                    ->options(ContractType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(2),
                DatePicker::make('date')
                    ->label('Data contratto')
                    ->required()
                    ->columnSpan(2),
                TextInput::make('description')
                    ->label('Descrizione')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(6),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Numero'),
                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Tipo')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle'),
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
