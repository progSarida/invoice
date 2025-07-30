<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\PassivePaymentResource\Pages;
use App\Filament\Company\Resources\PassivePaymentResource\RelationManagers;
use App\Models\PassivePayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PassivePaymentResource extends Resource
{
    protected static ?string $model = PassivePayment::class;

    public static ?string $pluralModelLabel = 'Pagamenti';

    public static ?string $modelLabel = 'Pagamento';

    protected static ?string $navigationIcon = 'polaris-payment-icon';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    public static function getNavigationSort(): ?int
    {
        return 3;
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
            'index' => Pages\ListPassivePayments::route('/'),
            'create' => Pages\CreatePassivePayment::route('/create'),
            'edit' => Pages\EditPassivePayment::route('/{record}/edit'),
        ];
    }
}
