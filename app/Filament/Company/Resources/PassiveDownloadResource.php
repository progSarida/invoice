<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\PassiveDownloadResource\Pages;
use App\Filament\Company\Resources\PassiveDownloadResource\RelationManagers;
use App\Models\PassiveDownload;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PassiveDownloadResource extends Resource
{
    protected static ?string $model = PassiveDownload::class;

    public static ?string $pluralModelLabel = 'Scarichi fatture passive';

    public static ?string $modelLabel = 'Scarico fattura passiva';

    protected static ?string $navigationIcon = 'tabler-list-search';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    protected static ?int $navigationSort = 3;

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
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Data scarico')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('new_suppliers')
                    ->label('Nuovi fornitori')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('new_invoices')
                    ->label('Fatture scaricate')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('download_date_range')
                    ->form([
                        DatePicker::make('download_from_date')
                            ->label('Data scarico da'),
                        DatePicker::make('download_to_date')
                            ->label('Data scarico a'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['download_from_date'])) {
                            $query->whereDate('date', '>=', $data['download_from_date']);
                        }
                        if (! empty($data['download_to_date'])) {
                            $query->whereDate('date', '<=', $data['download_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['download_from_date'] && $data['download_to_date']) {
                            return "Data scarico dal {$data['download_from_date']} al {$data['download_to_date']}";
                        }
                        if ($data['download_from_date']) {
                            return "Data scarico dal {$data['download_from_date']}";
                        }
                        if ($data['download_to_date']) {
                            return "Data scarico al {$data['download_to_date']}";
                        }
                        return null;
                    }),
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListPassiveDownloads::route('/'),
            // 'create' => Pages\CreatePassiveDownload::route('/create'),
            // 'view' => Pages\ViewPassiveDownload::route('/{record}'),
            // 'edit' => Pages\EditPassiveDownload::route('/{record}/edit'),
        ];
    }
}
