<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ActTypeResource\Pages;
use App\Filament\Company\Resources\ActTypeResource\RelationManagers;
use App\Models\ActType;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ActTypeResource extends Resource
{
    protected static ?string $model = ActType::class;

    public static ?string $pluralModelLabel = 'Tipi di atto';

    public static ?string $modelLabel = 'Tipo do atto';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Controlla se mostrare questa risorsa nella navigazione
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (!$user) { return false;   }                                         // nessun utente autenticato

        // if ($user->is_admin) { return true; }                                // admin vedono sempre

        if ($tenant && $user->isManagerOf($tenant)) { return true; }            // manager del tenant corrente possono vedere

        return false;                                                           // utenti normali non vedono
    }

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
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActTypes::route('/'),
            'create' => Pages\CreateActType::route('/create'),
            'edit' => Pages\EditActType::route('/{record}/edit'),
        ];
    }
}
