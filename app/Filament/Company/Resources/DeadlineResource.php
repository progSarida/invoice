<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\DeadlineResource\Pages;
use App\Filament\Company\Resources\DeadlineResource\RelationManagers;
use App\Models\Deadline;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
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
            ->columns(5)
            ->schema([
                TextInput::make('description')
                    ->label('Descrizione')
                    ->columnSpan(3),
                Placeholder::make('')
                    ->columnSpan(1),
                Toggle::make('dispatched')
                    ->label('Rispetatta')
                    ->live()
                    ->default(false)
                    ->columnSpan(1),
                DatePicker::make('date')
                    ->label('Scadenza pagamento')
                    ->disabled()
                    ->columnSpan(1),
                TextInput::make('amount')
                    ->label('Totale')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->columnSpan(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Totale')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('dispatched')
                    ->label('Rispetatta')
                    ->sortable()
                    ->afterStateUpdated(function (\App\Models\Deadline $record, bool $state) {
                        if ($state) {
                            $record->dispatched = true;
                        } else {
                            $record->dispatched = false;
                        }
                        $record->save();
                    }),
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
