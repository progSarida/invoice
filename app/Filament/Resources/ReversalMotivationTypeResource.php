<?php

namespace App\Filament\Resources;

use App\Enums\ReversalGroupType;
use App\Filament\Resources\ReversalMotivationTypeResource\Pages;
use App\Filament\Resources\ReversalMotivationTypeResource\RelationManagers;
use App\Models\Invoice;
use App\Models\ReversalMotivationType;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReversalMotivationTypeResource extends Resource
{
    protected static ?string $model = ReversalMotivationType::class;
    public static ?string $pluralModelLabel = 'Motivazioni note di credito';
    public static ?string $modelLabel = 'Motivazioni note di credito';
    protected static ?string $navigationIcon = 'tabler-arrow-back-up';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Select::make('reversal_group_type')
                    ->label('Gruppo documenti')
                    ->required()
                    ->options(ReversalGroupType::class)
                    ->columnSpan(3),
                TextInput::make('name')->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(7),
                Forms\Components\TextInput::make('order')->label('Posizione')
                    ->required()
                    ->columnspan(2),
                TextInput::make('description')->label('Descrizione')
                    ->maxLength(255)
                    ->columnspan(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order')->label('Posizione')
                    ->sortable(),
                TextColumn::make('reversal_group_type')->label('Gruppo annullamento')
                    ->searchable()
                    ->color('black')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name')->label('Nome')
                    ->searchable(),
                TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('info')
                    ->label('Bloccato')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->tooltip('Impossibile modificare o eliminare: servizio in uso nei contratti. Rivolgersi alla programmazione.')
                    ->visible(function ($record) {
                        if (!$record) return false;
                        return Invoice::where('reversal_motivation_type_id', $record->id)->exists();
                    })
                    ->action(fn () => null),
                Tables\Actions\EditAction::make()
                    ->visible(function ($record) {
                        if (!$record) return false;
                        $inContracts = Invoice::where('reversal_motivation_type_id', $record->id)->exists();
                        return !$inContracts;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function ($record) {
                        if (!$record) return false;
                        $inContracts = Invoice::where('reversal_motivation_type_id', $record->id)->exists();
                        return !$inContracts;
                    }),
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
            'index' => Pages\ListReversalMotivationTypes::route('/'),
            'create' => Pages\CreateReversalMotivationType::route('/create'),
            'edit' => Pages\EditReversalMotivationType::route('/{record}/edit'),
        ];
    }
}
