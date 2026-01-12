<?php

namespace App\Filament\Company\Resources\BailResource\RelationManagers;

use App\Enums\BailStatus;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class BailDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'bailDetails';

    protected static ?string $title = 'Storico polizza';

    protected static ?string $modelLabel = 'Rinnovo polizza';

    public function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                Forms\Components\DatePicker::make('bill_start')->label('Inizio Polizza')
                    ->required()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->default(function(){
                        $prev = $this->getOwnerRecord()->lastDetail;
                        if($prev) return \Carbon\Carbon::parse($prev->bill_start)->addYear();
                        else null;
                    })
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('bill_deadline')->label('Scadenza Polizza')
                    ->required()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->default(function(){
                        $prev = $this->getOwnerRecord()->lastDetail;
                        if($prev) return \Carbon\Carbon::parse($prev->bill_deadline)->addYear();
                        else null;
                    })
                    ->columnSpan(2),

                Placeholder::make('')
                    ->label('')
                    ->columnSpan(2),

                Forms\Components\DatePicker::make('receipt_date')->label('Data Quietanza')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2)
                    ->nullable(),
                Forms\Components\FileUpload::make('attachment_path')->label('Quietanza')
                    ->live()
                    // ->disk('public')
                    // ->directory('bail/bill-attachments')
                    ->directory(function (Get $get) {
                        $ownerRecord = $this->getOwnerRecord();
                        $client = Client::find($ownerRecord->client_id)->denomination;
                        return "bail/bill-attachments/{$client}_{$ownerRecord->bill_number}";
                    })
                    // ->visibility('public')
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file, Get $get): string => 'Quietanza_' . $get('receipt_date') . '.' . $file->getClientOriginalExtension()
                    )
                    ->columnSpan(3)
                    ->extraAttributes(['class' => 'file-upload-with-preview']),
                Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('view_receipt')
                        // ->label('Visualizza')
                        ->label('Quietanza')
                        ->tooltip('Visualizza quietanza')
                        ->icon('heroicon-o-eye')
                        // ->url(fn($record): ?string => $record && $record->attachment_path ? Storage::url($record->attachment_path) : null)
                        ->url(fn($record): ?string => $record && $record->attachment_path ? Storage::temporaryUrl($record->attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->hidden(fn ($record) => !$record || !$record->attachment_path),
                ])
                ->columnSpan(1),

                Forms\Components\TextInput::make('premium')->label('Importo Premio')
                    ->columnSpan(2)
                    ->required()
                    // ->numeric()
                    ->live(onBlur: true)
                    ->debounce(1000)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->afterStateUpdated(function ($state, $component) {
                        $clean = preg_replace('/[^\d,\.-]/', '', $state);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    // ->nullable()
                    ->prefix('€'),
                Forms\Components\Select::make('bail_status')->label('Stato Pagamento')
                    ->columnSpan(2)
                    ->live()
                    ->options(\App\Enums\BailStatus::class)
                    ->nullable(),
                Forms\Components\DatePicker::make('pay_date')->label('In Data')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2)
                    ->nullable(),

                Forms\Components\DatePicker::make('release_date')->label('Data Svincolo')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2)
                    ->required()
                    ->visible(fn (Get $get) => $get('bail_status') === BailStatus::RELEASED->value),
                Forms\Components\FileUpload::make('release_path')->label('Attestazione di Svincolo')
                    ->live()
                    ->required()
                    // ->disk('public')
                    // ->directory('bail/bill-attachments')
                    ->directory(function (Get $get) {
                        $ownerRecord = $this->getOwnerRecord();
                        $client = Client::find($ownerRecord->client_id)->denomination;
                        return "bail/bill-attachments/{$client}_{$ownerRecord->bill_number}";
                    })
                    // ->visibility('public')
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file, Get $get): string => 'Svincolo_' . $get('release_date') . '.' . $file->getClientOriginalExtension()
                    )
                    ->columnSpan(3)
                    ->extraAttributes(['class' => 'file-upload-with-preview'])
                    ->visible(fn (Get $get) => $get('bail_status') === BailStatus::RELEASED->value),
                Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('view_release')
                        // ->label('Visualizza')
                        ->label('Quietanza')
                        ->tooltip('Visualizza quietanza')
                        ->icon('heroicon-o-eye')
                        // ->url(fn($record): ?string => $record && $record->attachment_path ? Storage::url($record->attachment_path) : null)
                        ->url(fn($record): ?string => $record && $record->attachment_path ? Storage::temporaryUrl($record->attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->hidden(fn ($record) => !$record || !$record->release_path),
                ])
                ->columnSpan(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bill_start')
                    ->label('Inizio Polizza')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/A'),
                Tables\Columns\TextColumn::make('bill_deadline')
                    ->label('Scadenza Polizza')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/A'),
                Tables\Columns\TextColumn::make('premium')->label('Premio')
                    ->money('EUR', true, 'it_IT'),
                Tables\Columns\TextColumn::make('bail_status')
                    ->label('Stato')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Crea rinnovo polizza'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Visualizza rinnovo polizza'),
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica rinnovo polizza'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
