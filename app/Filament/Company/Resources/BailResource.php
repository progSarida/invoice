<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\BailResource\Pages;
use App\Filament\Company\Resources\BailResource\RelationManagers;
use App\Models\Bail;
use App\Models\Client;
use App\Models\NewContract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BailResource extends Resource
{
    protected static ?string $model = Bail::class;

    public static ?string $pluralModelLabel = 'Cauzioni';

    public static ?string $modelLabel = 'Cauzione';

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Fatturazione attiva';

    protected static ?string $recordTitleAttribute = 'insurance';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                    )
                    ->required()
                    ->searchable('denomination')
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(5),
                Forms\Components\Select::make('tax_type')->label('Tipo Entrata')
                    ->options(\App\Enums\TaxType::class)->afterStateUpdated(function (Get $get, Set $set) {
                        if(empty($get('client_id')) || empty($get('tax_type')))
                        $set('contract_id', null);
                    })
                    ->placeholder('')
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(2),
                Forms\Components\Select::make('contract_id')->label('Contratto')
                    ->relationship(
                        name: 'contract',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$get('client_id'))->where('tax_type',$get('tax_type'))
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})"
                    )
                    ->afterStateUpdated(function (Set $set, $state) {
                        if($state) {
                            $contract = NewContract::find($state);
                            $set('cig_code', $contract->cig_code);
                        }
                    })
                    ->disabled(fn(Get $get): bool => ! filled($get('client_id')) || ! filled($get('tax_type')))
                    ->searchable('denomination')
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('cig_code')->label('Codice Identificativo Gara')
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('insurance')->label('Assicurazione')
                    ->maxLength(255)
                    ->columnSpan(7),
                Forms\Components\TextInput::make('agency')->label('Agenzia')
                    ->maxLength(255)
                    ->columnSpan(5),
                Forms\Components\TextInput::make('bill_number')->label('Numero Polizza')
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('bill_date')->label('Data Polizza')
                    ->columnSpan(2),
                Forms\Components\FileUpload::make('bill_attachment_path')->label('Allegato Polizza')
                    ->directory('bail-attachments')
                    ->columnSpan(3),
                Forms\Components\DatePicker::make('bill_start')->label('Inizio Polizza')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('duration')->label('Durata')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('bill_deadline')->label('Scadenza Polizza')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('original_premium')->label('Importo Premio Originario')
                    ->columnSpan(3)
                    ->numeric()
                    ->prefix('€')
                    ->nullable(),
                Forms\Components\DatePicker::make('original_pay_date')->label('Data Pagamento Premio Originario')
                    ->columnSpan(3)
                    ->nullable(),
                Forms\Components\Select::make('bail_status')->label('Stato Cauzione')
                    ->columnSpan(3)
                    ->options(\App\Enums\BailStatus::class)
                    ->nullable(),
                Forms\Components\DatePicker::make('release_date')->label('Data Rilascio')
                    ->columnSpan(3)
                    ->nullable(),
                Forms\Components\TextInput::make('renew_premium')->label('Importo Rinnovo')
                    ->columnSpan(3)
                    ->numeric()
                    ->prefix('€')
                    ->nullable(),
                Forms\Components\DatePicker::make('renew_date')->label('Data Rinnovo')
                    ->columnSpan(3)
                    ->nullable(),
                Forms\Components\FileUpload::make('receipt_attachment_path')->label('Allegato Ricevuta Quietanza')
                    ->columnSpan(3)
                    ->directory('receipt-attachments')
                    ->nullable(),
                Forms\Components\Textarea::make('note')->label('Note')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract_id')
                    ->label('Contratto')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('cig_code')
                    ->label('CIG')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('tax_type')
                    ->label('Tipo Entrata')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
                Tables\Columns\TextColumn::make('insurance')
                    ->label('Assicurazione')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('bill_number')
                    ->label('Numero Polizza')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('bill_date')
                    ->label('Data Polizza')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('bill_start')
                    ->label('Inizio Polizza')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('bill_deadline')
                    ->label('Scadenza Polizza')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('original_premium')
                    ->label('Importo')
                    ->money('EUR')
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('bail_status')
                    ->label('Stato Cauzione')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tax_type')
                    ->options(\App\Enums\TaxType::class)
                    ->label('Tipo Entrata'),
                Tables\Filters\SelectFilter::make('bail_status')
                    ->options(\App\Enums\BailStatus::class)
                    ->label('Stato Cauzione'),
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
            'index' => Pages\ListBails::route('/'),
            'create' => Pages\CreateBail::route('/create'),
            'edit' => Pages\EditBail::route('/{record}/edit'),
        ];
    }
}
