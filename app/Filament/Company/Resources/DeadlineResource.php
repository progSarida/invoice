<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\DeadlineResource\Pages;
use App\Filament\Company\Resources\DeadlineResource\RelationManagers;
use App\Models\Deadline;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeadlineResource extends Resource
{
    protected static ?string $model = Deadline::class;

    public static ?string $pluralModelLabel = 'Scadenze';

    public static ?string $modelLabel = 'Scadenza';

    protected static ?string $navigationIcon = 'akar-schedule';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    public static function getNavigationSort(): ?int
    {
        return 4;
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
            'index' => Pages\ListDeadlines::route('/'),
            'create' => Pages\CreateDeadline::route('/create'),
            'edit' => Pages\EditDeadline::route('/{record}/edit'),
        ];
    }
}
