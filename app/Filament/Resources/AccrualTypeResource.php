<?php

namespace App\Filament\Resources;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use App\Filament\Resources\AccrualTypeResource\Pages;
use App\Filament\Resources\AccrualTypeResource\RelationManagers;
use App\Models\AccrualType;
use App\Models\NewContract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccrualTypeResource extends Resource
{
    protected static ?string $model = AccrualType::class;
    public static ?string $pluralModelLabel = 'Gestioni';
    public static ?string $modelLabel = 'Gestioni';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                Forms\Components\TextInput::make('name')->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(2),
                Forms\Components\TextInput::make('order')->label('Posizione')
                    ->required()
                    ->columnspan(1),
                Forms\Components\TextInput::make('description')->label('Descrizione')
                    ->maxLength(255)
                    ->columnspan(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')->label('Posizione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label('🔍 Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')->label('🔍 Descrizione')
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
            ->recordUrl(function ($record) {
                $inContracts = NewContract::whereJsonContains('accrual_types', $record->id)->exists();
                if ($inContracts) { return null; }
                return static::getUrl('edit', ['record' => $record]);
            })
            ->actions([
                Tables\Actions\Action::make('info')
                    ->label('Bloccato')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->tooltip('Impossibile modificare o eliminare: gestione in uso nei contratti. Rivolgersi alla programmazione.')
                    ->visible(function ($record) {
                        if (!$record) return false;
                        return NewContract::whereJsonContains('accrual_types', $record->id)->exists();
                    })
                    ->action(fn () => null),
                Tables\Actions\EditAction::make()
                    ->visible(function ($record) {
                        if (!$record) return false;
                        $inContracts = NewContract::whereJsonContains('accrual_types', $record->id)->exists();
                        return !$inContracts;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function ($record) {
                        if (!$record) return false;
                        $inContracts = NewContract::whereJsonContains('accrual_types', $record->id)->exists();
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
            'index' => Pages\ListAccrualTypes::route('/'),
            'create' => Pages\CreateAccrualType::route('/create'),
            'edit' => Pages\EditAccrualType::route('/{record}/edit'),
        ];
    }
}
