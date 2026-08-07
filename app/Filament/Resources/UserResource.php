<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Filament\Resources\UserResource\RelationManagers\RolesRelationManager;
use App\Models\User;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    public static ?string $pluralModelLabel = 'Utenti';
    public static ?string $modelLabel = 'Utente';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Tabelle';
    protected static ?int $navigationSort = 1;

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
                // Forms\Components\Toggle::make('is_admin')
                //     ->label('Amministratore')
                //     ->dehydrated(fn ($state) => filled($state))
                //     ->helperText(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord ? '' : ''),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('🔍 Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')->label('🔍 Email')
                    ->searchable(),
                // Tables\Columns\ToggleColumn::make('is_admin')
                //     ->label('Admin')
                //     ->onIcon('heroicon-s-shield-check')
                //     ->offIcon('heroicon-s-shield-exclamation')
                //     ->onColor('success')
                //     ->offColor('danger'),
            Tables\Columns\TextColumn::make('companyRoles.name')
                    ->label('Ruolo')
                    ->badge()
                    ->separator(', '),
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
            RolesRelationManager::class
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
}
