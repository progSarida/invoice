<?php

namespace App\Filament\Company\Resources;

use App\Enums\NotifyType;
use App\Filament\Company\Resources\SendTypeResource\Pages;
use App\Filament\Company\Resources\SendTypeResource\RelationManagers;
use App\Models\SendType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SendTypeResource extends Resource
{
    protected static ?string $model = SendType::class;
    public static ?string $pluralModelLabel = 'Tipi di spedizione';
    public static ?string $modelLabel = 'Tipo di spedizione';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'name';

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
                    ->columnspan(2),
                Forms\Components\Select::make('notify_type')->label('Tipo notifica')
                    ->options(NotifyType::class)
                    ->required()
                    ->columnspan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')->label('Posizione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notify_type')->label('Tipo notifica'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListSendTypes::route('/'),
            'create' => Pages\CreateSendType::route('/create'),
            'edit' => Pages\EditSendType::route('/{record}/edit'),
            'view' => Pages\ViewSendType::route('/{record}'),
        ];
    }
}
