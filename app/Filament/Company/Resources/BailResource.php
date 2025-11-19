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
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class BailResource extends Resource
{
    protected static ?string $model = Bail::class;
    public static ?string $pluralModelLabel = 'Cauzioni';
    public static ?string $modelLabel = 'Cauzione';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Cauzioni';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'bill_number';

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
                Forms\Components\Select::make('tax_types') // MODIFICA: Rinominato da 'tax_type' a 'tax_types'
                    ->label('Tipo Entrata')
                    ->options(\App\Enums\TaxType::class)
                    ->multiple() // MODIFICA: Aggiunto per consentire selezione multipla
                    ->afterStateUpdated(function (Get $get, Set $set) { // MODIFICA: Aggiornato per gestire array
                        if (empty($get('client_id')) || empty($get('tax_types'))) {
                            $set('contract_id', null);
                        }
                    })
                    ->placeholder('')
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(2),
                Forms\Components\Select::make('contract_id')->label('Contratto')
                    ->relationship(
                        name: 'contract',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->where('client_id', $get('client_id'))
                            ->when($get('tax_types'), function ($q, $taxTypes) {
                                foreach ($taxTypes as $taxType) {
                                    $q->whereJsonContains('tax_types', $taxType);
                                }
                            })
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})\nCIG: ({$record->cig_code})"
                    )
                    ->afterStateUpdated(function (Set $set, $state) {
                        if ($state) {
                            $contract = NewContract::find($state);
                            $set('cig_code', $contract->cig_code);
                        }
                    })
                    ->disabled(fn (Get $get): bool => !filled($get('client_id')) || !filled($get('tax_types')))
                    ->searchable('cig_code')
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('cig_code')->label('CIG')
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\Select::make('insurance_id')
                    ->label('Assicurazione')
                    ->required()
                    ->options(function () {
                        return \App\Models\Insurance::query()
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('agency_id', null);
                    })
                    ->columnSpan(4),
                Forms\Components\Select::make('agency_id')
                    ->label('Agenzia')
                    ->required()
                    ->options(function () {
                        return \App\Models\Agency::query()
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->options(function (callable $get) {
                        $insuranceId = $get('insurance_id');
                        return \App\Models\Agency::query()
                            ->when($insuranceId, fn ($query) => $query->where('insurance_id', $insuranceId))
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $agency = \App\Models\Agency::find($state);
                            if ($agency && $agency->insurance_id) {
                                $set('insurance_id', $agency->insurance_id);
                            }
                        }
                    })
                    ->columnSpan(4),
                Forms\Components\TextInput::make('bill_number')->label('Numero Polizza')
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('bill_date')->label('Data Polizza')
                    ->columnSpan(2),
                Forms\Components\FileUpload::make('bill_attachment_path')->label('Allegato Polizza')
                    ->live()
                    ->disk('public')
                    ->directory('bail/bill-attachments')
                    ->visibility('public')
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file, Get $get): string => Client::find($get('client_id'))->denomination . '_' . $get('bill_number') . '.' . $file->getClientOriginalExtension()
                    )
                    ->columnSpan(3)
                    ->extraAttributes(['class' => 'file-upload-with-preview']),
                Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('view_bill_attachment')
                        ->label('Visualizza')
                        ->icon('heroicon-o-eye')
                        ->url(fn($record): ?string => $record && $record->bill_attachment_path ? Storage::url($record->bill_attachment_path) : null)
                        ->openUrlInNewTab()
                        ->hidden(fn ($record) => !$record || !$record->bill_attachment_path),
                ])
                ->columnSpan(2),
                Forms\Components\TextInput::make('year_duration')->label('Anni')
                    ->maxLength(255)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('month_duration')->label('Mesi')
                    ->maxLength(255)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('day_duration')->label('Giorni')
                    ->maxLength(255)
                    ->columnSpan(1),
                Forms\Components\DatePicker::make('bill_start')->label('Inizio Polizza')
                    ->columnSpan(2),
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
                    ->columnSpan(2)
                    ->numeric()
                    ->prefix('€')
                    ->nullable(),
                Forms\Components\DatePicker::make('renew_date')->label('Data Rinnovo')
                    ->columnSpan(2)
                    ->nullable(),
                Forms\Components\DatePicker::make('receipt_date')->label('Data Ricevuta')
                    ->columnSpan(2)
                    ->nullable(),
                Forms\Components\FileUpload::make('receipt_attachment_path')->label('Allegato Ricevuta Pagamento')
                    ->live()
                    ->disk('public')
                    ->directory('bail/receipt-attachments')
                    ->visibility('public')
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file, Get $get): string => Client::find($get('client_id'))->denomination . '_' . $get('bill_number') . '.' . $file->getClientOriginalExtension()
                    )
                    ->columnSpan(3)
                    ->extraAttributes(['class' => 'file-upload-with-preview']),
                Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('view_receipt_attachment')
                        ->label('Visualizza')
                        ->icon('heroicon-o-eye')
                        ->url(fn($record): ?string => $record && $record->receipt_attachment_path ? Storage::url($record->receipt_attachment_path) : null)
                        ->openUrlInNewTab()
                        ->hidden(fn ($record) => !$record || !$record->receipt_attachment_path),
                ])
                ->columnSpan(2),
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
                Tables\Columns\TextColumn::make('cig_code')
                    ->label('CIG')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('tax_types') // MODIFICA: Rinominato da 'tax_type' a 'tax_types'
                    ->label('Tipo Entrata')
                    ->badge() // MODIFICA: Aggiunto per visualizzare come badge
                    ->color(fn (string $state): string => match ($state) { // MODIFICA: Aggiunto colori personalizzati
                        'CDS' => 'info',
                        'ICI' => 'warning',
                        'IMU' => 'success',
                        'LIBERO' => 'danger',
                        'PARK' => 'info',
                        'PUB' => 'info',
                        'TARI' => 'primary',
                        'TEP' => 'primary',
                        'TOSAP' => 'warning',
                        default => 'gray'
                    })
                    ->separator(', ') // MODIFICA: Aggiunto per separare valori multipli
                    ->searchable(query: function (Builder $query, string $search) { // MODIFICA: Aggiunto whereJsonContains per ricerca
                        $query->whereJsonContains('tax_types', $search);
                    }),
                Tables\Columns\TextColumn::make('insurance.name')
                    ->label('Assicurazione')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bill_number')
                    ->label('Numero Polizza')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('bill_deadline')
                    ->label('Scadenza Polizza')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/A'),
                Tables\Columns\TextColumn::make('remain_days')
                    ->label('Giorni rimanenti')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('bill_deadline', $direction))
                    ->getStateUsing(function ($record) {
                        if (!$record->bill_deadline) {
                            return 'N/A';
                        }
                        try {
                            $deadline = \Carbon\Carbon::parse($record->bill_deadline);
                            $today = \Carbon\Carbon::today();
                            $daysRemaining = $today->diffInDays($deadline, false);
                            return $daysRemaining;
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Errore nel calcolo dei giorni rimanenti: ' . $e->getMessage());
                            return 'Errore';
                        }
                    }),
                Tables\Columns\TextColumn::make('bail_status')
                    ->label('Stato Cauzione')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('insurance')
                    ->options(function () {
                        return \App\Models\Insurance::all()->pluck('name', 'id')->toArray();
                    })
                    ->label('Assicurazione')
                    ->query(function ($query, $data) {
                        if (!empty($data['value'])) {
                            $query->where('insurance_id', $data);
                        }
                    }),
                Tables\Filters\SelectFilter::make('tax_types')
                    ->options(\App\Enums\TaxType::class)
                    ->multiple()
                    ->label('Entrata')
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            foreach ($data as $taxType) {
                                $query->whereJsonContains('tax_types', $taxType);
                            }
                        }
                    }),
                Tables\Filters\SelectFilter::make('bail_status')
                    ->options(\App\Enums\BailStatus::class)
                    ->label('Stato')
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->where('bail_status', $data['value']);
                        }
                    }),
                Tables\Filters\SelectFilter::make('expiration_status')
                    ->label('Stato Scadenza')
                    ->options([
                        '' => 'Tutti',
                        'expired' => 'Scaduti',
                        'expired_not_paid' => 'Scaduti e non pagati',
                        'expired_not_released' => 'Scaduti e non svincolati',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'expired' => $query->where('bill_deadline', '<', now()),
                            'expired_not_paid' => $query->where('bill_deadline', '<', now())->whereNull('original_pay_date'),
                            'expired_not_released' => $query->where('bill_deadline', '<', now())->whereNull('release_date'),
                            default => $query,
                        };
                    }),
                Tables\Filters\Filter::make('not_paid')
                    ->form([
                        Forms\Components\Checkbox::make('not_paid')
                            ->label('Senza data di pagamento'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['not_paid'] ? $query->whereNull('original_pay_date') : $query;
                    }),
                Tables\Filters\Filter::make('not_receipt')
                    ->form([
                        Forms\Components\Checkbox::make('not_receipt')
                            ->label('Senza allegato pagamento'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['not_receipt'] ? $query->whereNull('receipt_attachment_path') : $query;
                    }),
            ], layout: FiltersLayout::Modal)->filtersFormColumns(3)
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
