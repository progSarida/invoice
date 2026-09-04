<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\DeadlineResource\Pages;
use App\Filament\Company\Resources\DeadlineResource\RelationManagers;
use App\Models\Deadline;
use App\Services\CurrencyService;
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

    protected static ?string $navigationIcon = 'tabler-calendar-clock';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    protected static ?int $navigationSort = 4;

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
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->columnSpan(1),
                TextInput::make('amount')
                    ->label('Totale')
                    ->live(onBlur: true)
                    // ->debounce(2000)
                    ->extraInputAttributes(['class' => 'text-right'])
                    // ->afterStateUpdated(function ($state, $component) {
                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                    //     }
                    //     else {
                    //         $amount = $state ?? 0;
                    //     }
                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                    //     $number = str_replace(',', '.', $clean);
                    //     $float = floatval($number);
                    //     $formatted = number_format($float, 2, ',', '.');
                    //     $component->state($formatted);
                    // })
                    ->afterStateUpdated(function ($state, $component) {
                        $float = CurrencyService::parseNumber($state);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                    ->suffix('€')
                    ->columnSpan(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('🔍 Descrizione')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('🔍 Totale')
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
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListDeadlines::route('/'),
            'create' => Pages\CreateDeadline::route('/create'),
            'edit' => Pages\EditDeadline::route('/{record}/edit'),
            'view' => Pages\ViewDeadline::route('/{record}'),
        ];
    }
}
