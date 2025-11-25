<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ContractDetailResource\Pages;
use App\Filament\Company\Resources\ContractDetailResource\RelationManagers;
use App\Models\ContractDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContractDetailResource extends Resource
{
    protected static ?string $model = ContractDetail::class;

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
            'index' => Pages\ListContractDetails::route('/'),
            'create' => Pages\CreateContractDetail::route('/create'),
            'edit' => Pages\EditContractDetail::route('/{record}/edit'),
        ];
    }
}
