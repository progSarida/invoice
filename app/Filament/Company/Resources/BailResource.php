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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Query\JoinClause;

class BailResource extends Resource
{
    protected static ?string $model = Bail::class;
    public static ?string $pluralModelLabel = 'Cauzioni';
    public static ?string $modelLabel = 'Cauzione';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Cauzioni';
    protected static ?int $navigationSort = 1;
    protected static ?int $navigationGroupSort = 3;
    protected static ?string $recordTitleAttribute = 'bill_number';

    public static function form(Form $form): Form
    {
        // 1. Definiamo la subquery per trovare l'ultima data di dettaglio per ogni contratto
        // Lo facciamo fuori dalle closure principali per riutilizzarlo e per chiarezza
        $latestDetailSubquery = \App\Models\ContractDetail::query()
            ->selectRaw('contract_id, MAX(date) as latest_detail_date')
            ->groupBy('contract_id')
            ->toBase();

        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    // ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getSearchResultsUsing(function (string $search) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query base con le stesse condizioni del relationship
                        $query = Client::query();

                        // Cerca separatori (spazio, virgola, slash, trattino)
                        $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                        if (count($parts) >= 2) {
                            // Cerca ogni "parte" all'interno del campo denomination
                            $query->where(function ($q) use ($parts) {
                                foreach ($parts as $part) {
                                    $q->where('denomination', 'LIKE', "%{$part}%");
                                }
                            });
                        } elseif (count($parts) === 1) {
                            // Un solo valore: cerca SOLO match esatto in number o year
                            $value = $parts[0];
                            $query->where(function ($q) use ($value) {
                                $q->where('denomination', 'LIKE', "%{$value}%");
                            });
                        }

                        return $query
                            ->orderBy('denomination', 'asc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $subtype = $record->subtype->getLabel() ?? 'Cliente sconosciuto';
                                $denomination = $record->denomination ?? 'N/A';
                                $label = strtoupper("{$subtype}") . " - $denomination";

                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function (?int $value) {
                        if (!$value) {
                            return null;
                        }
                        $record = Client::find($value);

                        if (!$record) {
                            return null;
                        }

                        return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                    })
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
                // Forms\Components\Select::make('contract_id')->label('Contratto')
                //     ->relationship(
                //         name: 'contract',
                //         modifyQueryUsing: fn (Builder $query, Get $get) => $query
                //             ->where('client_id', $get('client_id'))
                //             ->when($get('tax_types'), function ($q, $taxTypes) {
                //                 foreach ($taxTypes as $taxType) {
                //                     $q->whereJsonContains('tax_types', $taxType);
                //                 }
                //             })
                //     )
                //     ->getOptionLabelFromRecordUsing(
                //         fn (Model $record) => "{$record->office_name} ({$record->office_code})\nCIG: ({$record->cig_code})"
                //     )
                //     ->afterStateUpdated(function (Set $set, $state) {
                //         if ($state) {
                //             $contract = NewContract::find($state);
                //             $set('cig_code', $contract->cig_code);
                //         }
                //     })
                //     ->disabled(fn (Get $get): bool => !filled($get('client_id')) || !filled($get('tax_types')))
                //     ->searchable('cig_code')
                //     ->live()
                //     ->preload()
                //     ->optionsLimit(5)
                //     ->columnSpan(3),
                Forms\Components\Select::make('contract_id')->label('Contratto')
                    ->relationship(
                        name: 'contract',
                        modifyQueryUsing: function (Builder $query, Get $get, $livewire) use ($latestDetailSubquery) {
                            $query->where('client_id', $get('client_id'))
                                ->when($get('tax_types'), function ($q, $taxTypes) {
                                    foreach ($taxTypes as $taxType) {
                                        $q->whereJsonContains('tax_types', $taxType);
                                    }
                                });

                            // *** JOIN con subquery per calculated_year ***
                            $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                                $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                            });

                            // Applico i filtri di validità solo in fase di creazione
                            if ($livewire instanceof \Filament\Resources\Pages\CreateRecord) {
                                $query->where(function ($q) {
                                    $q->whereNull('start_validity_date')
                                        ->orWhere(function ($q2) {
                                            $q2->where('start_validity_date', '<=', today())
                                                ->where('end_validity_date', '>=', today());
                                        });
                                });
                            }

                            // Seleziona tutte le colonne e aggiungi calculated_year
                            $query->select('new_contracts.*')
                                ->selectRaw('
                                    COALESCE(
                                        YEAR(new_contracts.start_validity_date),
                                        YEAR(latest_details.latest_detail_date)
                                    ) AS calculated_year'
                                )
                                ->distinct();

                            // Ordina per anno calcolato DESC
                            $query->orderByRaw('calculated_year DESC');

                            return $query;
                        }
                    )
                    ->getSearchResultsUsing(function (string $search, Get $get) use ($latestDetailSubquery) {
                        // Pulisci la stringa di ricerca
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query di base con JOIN
                        $query = \App\Models\NewContract::query();

                        $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                            $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                        });

                        // Filtri di base
                        $query->where('client_id', $get('client_id'))
                            ->when($get('tax_types'), function ($q, $taxTypes) {
                                foreach ($taxTypes as $taxType) {
                                    $q->whereJsonContains('tax_types', $taxType);
                                }
                            });

                        // Filtro di ricerca
                        $query->where(function ($q) use ($search) {
                            // Cerca per CIG CODE
                            $q->where('new_contracts.cig_code', 'LIKE', "%{$search}%");

                            // Cerca per ANNO CALCOLATO (se numerico)
                            if (is_numeric($search)) {
                                $q->orWhereRaw('
                                    YEAR(COALESCE(
                                        new_contracts.start_validity_date,
                                        latest_details.latest_detail_date
                                    )) = ?', [$search]
                                );
                            }
                        });

                        // Selezione colonne
                        $query->select('new_contracts.*')
                            ->selectRaw('
                                COALESCE(
                                    YEAR(new_contracts.start_validity_date),
                                    YEAR(latest_details.latest_detail_date)
                                ) AS calculated_year'
                            )
                            ->distinct();

                        // Ordinamento
                        $query->orderByRaw('calculated_year DESC')
                            ->orderBy('new_contracts.id', 'DESC');

                        // Risultati
                        return $query
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $label = "{$record->office_name} ({$record->office_code})\nCIG: ({$record->cig_code}) - {$record->calculated_year}";
                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})\nCIG: ({$record->cig_code}) - {$record->calculated_year}"
                    )
                    ->afterStateUpdated(function (Set $set, $state) {
                        if ($state) {
                            $contract = NewContract::find($state);
                            $lastDetail = $contract->lastDetail()->first();

                            // Imposta cig_code come nella select semplice
                            $set('cig_code', $contract->cig_code);

                            // Controllo dettagli come nel codice complesso
                            if (!$lastDetail) {
                                Notification::make()
                                    ->title('Attenzione! E\' stato selezionato un contratto senza dettagli.')
                                    ->warning()
                                    ->duration(6000)
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai al contratto')
                                            ->url(NewContractResource::getUrl('edit', ['record' => $contract->id]))
                                            ->openUrlInNewTab()
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                        }
                    })
                    ->disabled(fn (Get $get): bool => !filled($get('client_id')) || !filled($get('tax_types')))
                    ->searchable()
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
                    ->extraInputAttributes(['class' => 'text-center'])
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
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(1),
                Forms\Components\TextInput::make('month_duration')->label('Mesi')
                    ->maxLength(255)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(1),
                Forms\Components\TextInput::make('day_duration')->label('Giorni')
                    ->maxLength(255)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(1),
                Forms\Components\DatePicker::make('bill_start')->label('Inizio Polizza')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('bill_deadline')->label('Scadenza Polizza')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2),
                Forms\Components\TextInput::make('original_premium')->label('Importo Premio Originario')
                    ->columnSpan(3)
                    // ->numeric()
                    ->live(onBlur: true)
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
                    ->prefix('€')
                    ->nullable(),
                Forms\Components\DatePicker::make('original_pay_date')->label('Data Pagamento Premio Originario')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(3)
                    ->nullable(),
                Forms\Components\Select::make('bail_status')->label('Stato Cauzione')
                    ->columnSpan(3)
                    ->options(\App\Enums\BailStatus::class)
                    ->nullable(),
                Forms\Components\DatePicker::make('release_date')->label('Data Rilascio')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(3)
                    ->nullable(),
                Forms\Components\TextInput::make('renew_premium')->label('Importo Rinnovo')
                    ->columnSpan(2)
                    ->live(onBlur: true)
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
                    // ->numeric()
                    ->prefix('€')
                    ->nullable(),
                Forms\Components\DatePicker::make('renew_date')->label('Data Rinnovo')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2)
                    ->nullable(),
                Forms\Components\DatePicker::make('receipt_date')->label('Data Ricevuta')
                    ->extraInputAttributes(['class' => 'text-center'])
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
            'index' => Pages\ListBails::route('/'),
            'create' => Pages\CreateBail::route('/create'),
            'edit' => Pages\EditBail::route('/{record}/edit'),
            'view' => Pages\ViewBail::route('/{record}'),
        ];
    }
}
