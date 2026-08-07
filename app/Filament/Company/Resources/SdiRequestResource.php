<?php

namespace App\Filament\Company\Resources;

use App\Enums\SdiRequestType;
use App\Filament\Company\Resources\SdiRequestResource\Pages;
use App\Filament\Company\Resources\SdiRequestResource\RelationManagers;
use App\Models\SdiRequest;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SdiRequestResource extends Resource
{
    protected static ?string $model = SdiRequest::class;

    public static ?string $pluralModelLabel = 'Richieste stato SDI';

    public static ?string $modelLabel = 'Richiesta stato SDI';

    protected static ?string $navigationIcon = 'tabler-clock-search';

    protected static ?string $navigationGroup = 'Fatturazione attiva';

    protected static ?int $navigationSort = 5;

    protected static ?int $navigationGroupSort = 2;

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
            ->defaultSort('request_date', 'desc')
            ->columns([
            // Tipo di richiesta con Badge e Icona
            TextColumn::make('sdi_request_type')
                ->label('Tipo')
                ->badge()
                ->color(fn (SdiRequestType $state): string => match ($state) {
                    SdiRequestType::MASS => 'danger',
                    SdiRequestType::SINGLE => 'warning',
                })
                // ->icon(fn (SdiRequestType $state): string => match ($state) {
                //     SdiRequestType::MASS => 'heroicon-m-layers',
                //     SdiRequestType::SINGLE => 'heroicon-m-document',
                // })
                ,

            // Data con formato leggibile
            TextColumn::make('request_date')
                ->label('Data Richiesta')
                ->date('d/m/Y')
                ->sortable(),

            // Riferimento fattura (usa il numero della fattura invece dell'ID)
            TextColumn::make('invoice_number') // Assumendo che Invoice abbia 'number'
                ->label('🔍 Numero Fattura')
                ->getStateUsing(function (SdiRequest $record): string {
                    // Verifichiamo che la relazione esista per evitare errori su record null
                    return $record->invoice ? $record->invoice->getNewInvoiceNumber() : '';
                })
                ->searchable() // Nota: la ricerca non funzionerà automaticamente su metodi calcolati
                ->sortable(false),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('sdi_request_type')
                ->options(SdiRequestType::class)
            ->label('Filtra per Tipo'),
            Filter::make('download_date_range')
                ->form([
                    DatePicker::make('download_from_date')
                        ->label('Data richiesta da'),
                    DatePicker::make('download_to_date')
                        ->label('Data richiesta a'),
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
                        return "Data richiesta dal {$data['download_from_date']} al {$data['download_to_date']}";
                    }
                    if ($data['download_from_date']) {
                        return "Data richiesta dal {$data['download_from_date']}";
                    }
                    if ($data['download_to_date']) {
                        return "Data richiesta al {$data['download_to_date']}";
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
            'index' => Pages\ListSdiRequests::route('/'),
            // 'create' => Pages\CreateSdiRequest::route('/create'),
            // 'edit' => Pages\EditSdiRequest::route('/{record}/edit'),
        ];
    }
}
