<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SdiNotificationResource\Pages;
use App\Filament\Company\Resources\SdiNotificationResource\RelationManagers;
use App\Models\SdiNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SdiNotificationResource extends Resource
{
    protected static ?string $model = SdiNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function shouldRegisterNavigation(): bool
    {
        return false;                                                                                   // nascondo la risorsa dal menu di navigazione
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListSdiNotifications::route('/'),
            'create' => Pages\CreateSdiNotification::route('/create'),
            'edit' => Pages\EditSdiNotification::route('/{record}/edit'),
        ];
    }
}
