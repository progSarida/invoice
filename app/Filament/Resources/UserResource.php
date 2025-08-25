<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static ?string $pluralModelLabel = 'Utenti';

    public static ?string $modelLabel = 'Utente';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label('Nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')->label('Password')
                    ->password()
                    ->confirmed()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                Forms\Components\TextInput::make('password_confirmation')
                    ->password()
                    ->label('Conferma password'),
                Forms\Components\Toggle::make('is_admin')
                    ->label('Amministratore')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord ? '' : ''),

                Forms\Components\Section::make('Aziende e Permessi')->schema([
                    Repeater::make('company_assignments')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('company_id')
                                ->label('Azienda')
                                ->options(Company::all()->pluck('name', 'id'))
                                ->required()
                                ->distinct(),
                            Forms\Components\Toggle::make('is_manager')
                                ->label('Manager')
                                ->helperText('Ha privilegi speciali per questa azienda'),
                        ])
                        ->columns(2)
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string =>
                            Company::find($state['company_id'])?->name ?? 'Nuova Assegnazione'
                        )
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                            return $data;
                        })
                        ->saveRelationshipsUsing(function ($component, $state, $record) {
                            $record->companies()->detach();

                            foreach ($state as $assignment) {
                                if (isset($assignment['company_id'])) {
                                    $record->companies()->attach($assignment['company_id'], [
                                        'is_manager' => $assignment['is_manager'] ?? false,
                                    ]);
                                }
                            }
                        })
                        ->afterStateHydrated(function ($component, $state, $record) {
                            if (!$record || !$record->exists) {
                                return;
                            }

                            $companies = $record->companies()->get();
                            $assignments = [];

                            foreach ($companies as $company) {
                                $assignments[] = [
                                    'company_id' => $company->id,
                                    'is_manager' => (bool) $company->pivot->is_manager,
                                ];
                            }

                            $component->state($assignments);
                        }),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')->label('Email')
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('is_admin')
                    ->label('Admin')
                    ->onIcon('heroicon-s-shield-check')
                    ->offIcon('heroicon-s-shield-exclamation')
                    ->onColor('success')
                    ->offColor('danger'),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Parametri';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
}
