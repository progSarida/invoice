<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\BankAccountResource\Pages;
use App\Filament\Company\Resources\BankAccountResource\RelationManagers;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    public static ?string $pluralModelLabel = 'Conti bancari';

    public static ?string $modelLabel = 'Conto bancario';

    protected static ?string $navigationIcon = 'clarity-bank-line';

    protected static ?string $navigationGroup = 'Gestione';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ["Iban"=>$record->iban, "bic"=>$record->bic];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return BankAccountResource::getUrl('edit', ['record' => $record]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Denominazione')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(4),
                Forms\Components\TextInput::make('holder')
                    ->label('Intestatario')
                    ->required()
                    ->maxLength(255)
                    ->default(fn () => filament()->getTenant()?->name)
                    ->columnspan(4),
                Forms\Components\TextInput::make('number')
                    ->label('Conto corrente')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(4),
                Forms\Components\TextInput::make('iban')
                    ->label('IBAN')
                    ->required()
                    ->maxLength(27)
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Codice internazionale per identificare il conto bancario')
                    ->columnSpan(4),
                Forms\Components\TextInput::make('bic')
                    ->label('BIC')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(4),
                Forms\Components\TextInput::make('swift')
                    ->label('SWIFT')
                    ->required()
                    ->maxLength(255)
                    ->columnspan(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Denominazione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('holder')
                    ->label('Intestatario')
                    ->searchable(),
                Tables\Columns\TextColumn::make('iban')
                    ->label('IBAN')
                    ->searchable(),
                Tables\Columns\TextColumn::make('number')
                    ->label('N. conto')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('bic')
                //     ->label('BIC')
                //     ->searchable(),
                // Tables\Columns\TextColumn::make('swift')
                //     ->label('SWIFT')
                //     ->searchable(),
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
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
