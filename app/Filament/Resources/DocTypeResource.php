<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\DocType;
use App\Models\DocGroup;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\DocTypeResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\DocTypeResource\RelationManagers;

class DocTypeResource extends Resource
{
    protected static ?string $model = DocType::class;
    public static ?string $pluralModelLabel = 'Tipi documenti';
    public static ?string $modelLabel = 'Tipi documenti';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Select::make('doc_group_id')
                    ->label('Gruppo documenti')
                    ->required()
                    ->options(
                        DocGroup::all()->pluck('name', 'id')
                    )
                    ->columnSpan(3),
                TextInput::make('name')->label('Nome')
                    ->required()
                    ->maxLength(255)
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
                Tables\Columns\TextColumn::make('docGroup.name')
                    ->label('🔍 Gruppo documenti')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')->label('🔍 Nome')
                    ->searchable(),
                TextColumn::make('description')->label('🔍 Descrizione')
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
                SelectFilter::make('doc_group_id')->label('Gruppo documenti')->relationship('docGroup', 'name'),
            ])
            ->deferFilters()                                    // i filtri si applicano solo cliccando il pulsante
            ->filtersApplyAction(
                fn (Tables\Actions\Action $action) => $action
                    ->label('Applica filtri')
                    ->icon('heroicon-m-magnifying-glass')
                    // allineo il pulsante a destra del pannello dei filtri
                    ->extraAttributes(['style' => 'display: flex; width: fit-content; margin-inline-start: auto;']),
            )
            ->recordUrl(function ($record) {
                return null;
            })
            ->actions([
                Tables\Actions\Action::make('info')
                    ->label('Bloccato')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->tooltip('Impossibile modificare o eliminare. Rivolgersi alla programmazione.')
                    ->action(fn () => null)
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListDocTypes::route('/'),
            'create' => Pages\CreateDocType::route('/create'),
            'edit' => Pages\EditDocType::route('/{record}/edit'),
        ];
    }
}
