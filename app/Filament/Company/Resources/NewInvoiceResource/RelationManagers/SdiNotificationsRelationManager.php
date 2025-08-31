<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\RelationManagers;

use App\Enums\SdiStatus;
use App\Models\SdiNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SdiNotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'sdiNotifications';

    protected static ?string $pluralModelLabel = 'Notifiche Sdi';

    protected static ?string $modelLabel = 'Notifica Sdi';

    protected static ?string $title = 'Notifiche Sdi';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')->options(SdiStatus::class)->columnSpanFull(),
                Forms\Components\TextInput::make('code')->label('Codice')->columnSpan(1)
                    ->maxLength(255),
                Forms\Components\DatePicker::make('date')->native(false)->displayFormat('d/m/Y')->label('Data')->columnSpan(1),
                Forms\Components\TextInput::make('description')->label('Descrizione')->columnSpanFull()
                    ->maxLength(255),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    // ->badge()
                    ->formatStateUsing(function ( SdiNotification $sdi) {
                        return $sdi->status->getDescription();
                    }),
                Tables\Columns\TextColumn::make('code')->label('Codice'),
                Tables\Columns\TextColumn::make('date')->label('Data')
                    ->date('d F Y'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
