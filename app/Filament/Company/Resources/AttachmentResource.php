<?php

namespace App\Filament\Company\Resources;

use App\Enums\AttachmentType;
use App\Filament\Company\Resources\AttachmentResource\Pages;
use App\Filament\Company\Resources\AttachmentResource\RelationManagers;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\NewContract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class AttachmentResource extends Resource
{
    protected static ?string $model = Attachment::class;

    public static ?string $pluralModelLabel = 'Allegati';

    public static ?string $modelLabel = 'Allegato';

    protected static ?string $navigationIcon = 'ri-attachment-line';

    protected static ?string $navigationGroup = 'Generale';

    protected static ?int $navigationSort = 1;

    protected static ?int $navigationGroupSort = 1;

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
            ->columns([
                Tables\Columns\TextColumn::make('attachment_type')
                    ->label('Tipo allegato'),
                Tables\Columns\TextColumn::make('attachment_upload_date')
                    ->label('Data caricamento')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.denomination')
                    ->label('Cliente')
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract')
                    ->label('🔍 Contratto')
                    ->sortable()
                    ->searchable(
                        query: function ($query, $search) {
                            return $query->whereHas('contract', function ($query) use ($search) {
                                $query->where('cig_code', 'like', "%{$search}%");
                            });
                        }
                    )
                    ->getStateUsing(function ($record) {
                        $contract = NewContract::find($record->contract_id);
                        if($contract)
                            return "{$contract?->office_name} ({$contract?->office_code}) - TIPO: {$contract?->payment_type->getLabel()} - CIG: {$contract?->cig_code}";
                        else return '';
                    }),
                Tables\Columns\TextColumn::make('attachment_date')
                    ->label('Data allegato')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachment_filename')
                    ->label('Allegato'),
            ])
            ->filters([
                SelectFilter::make('client_id')->label('Cliente')
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
                                // $label = strtoupper("{$subtype}") . " - $denomination";
                                $label = $denomination;

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

                        // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                        return $record->denomination;
                    })
                    ->getOptionLabelFromRecordUsing(
                        // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                        fn (Model $record) => $record->denomination
                    )
                    ->searchable()
                    ->preload()
                    ->optionsLimit(5),
                SelectFilter::make('attachment_type')
                    ->label('Tipo allegato')
                    ->options(AttachmentType::class)
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Filter::make('interval_date')
                    ->label('Intervallo di date')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data inizio')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->placeholder('Seleziona data inizio')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data fine')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->placeholder('Seleziona data fine')
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('start_date'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!empty($data['start_date'])) {
                            $query->where('attachment_upload_date', '>=', $data['start_date']);
                        }
                        if (!empty($data['end_date'])) {
                            $query->where('attachment_upload_date', '<=', \Carbon\Carbon::parse($data['end_date'])->endOfDay());
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicator = null;

                        if (!empty($data['start_date']) && !empty($data['end_date'])) {
                            $indicator = 'Dal ' . \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y') . ' al ' . \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y');
                        } elseif (!empty($data['start_date'])) {
                            $indicator = 'Dal ' . \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y');
                        } elseif (!empty($data['end_date'])) {
                            $indicator = 'Al ' . \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y');
                        }

                        return $indicator ? [$indicator] : [];
                    }),
            ])
            ->deferFilters()                                    // i filtri si applicano solo cliccando il pulsante
            ->filtersApplyAction(
                fn (Tables\Actions\Action $action) => $action
                    ->label('Applica filtri')
                    ->icon('heroicon-m-magnifying-glass')
                    // allineo il pulsante a destra del pannello dei filtri
                    ->extraAttributes(['style' => 'display: flex; width: fit-content; margin-inline-start: auto;']),
            )
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica file')
                    ->icon('tabler-file-download')
                    ->iconSize('lg')
                    // ->url(fn($record): ?string => $record->attachment_path ? Storage::temporaryUrl($record->attachment_path,now()->addMinutes(1)) : null)
                    ->url(function ($record) {
                        if (! $record?->attachment_path) return null;

                        $disk = Storage::disk(config('filesystems.default'));

                        try {
                            // Se il driver supporta i link temporanei (es. S3), usa questo
                            return $disk->temporaryUrl($record->attachment_path, now()->addMinutes(1));
                        } catch (\RuntimeException | \InvalidArgumentException $e) {
                            // Se sei in locale (driver local/public), usa l'URL standard
                            // Assicurati di aver lanciato 'php artisan storage:link'
                            return $disk->url($record->attachment_path);
                        }
                    })
                    ->openUrlInNewTab()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('download_all')
                        ->label('Scarica selezionati')
                        ->icon('tabler-download')
                        ->action(function ($records) {
                            $disk = config('filesystems.default');
                            $storage = Storage::disk($disk);
                            $zip = new \ZipArchive();
                            $zipFileName = 'allegati_' . now()->format('Y-m-d_His') . '.zip';
                            $zipFilePath = $storage->path($zipFileName);

                            if ($zip->open($zipFilePath, \ZipArchive::CREATE) === TRUE) {
                                foreach ($records as $record) {
                                    $filePath = $record->attachment_path;
                                    if ($filePath && $storage->exists($filePath)) {
                                        $fullPath = $storage->path($filePath);
                                        $fileName = basename($filePath);

                                        // aggiung prefisso per evitare duplicati
                                        $uniqueFileName = $record->id . '_' . $fileName;
                                        $zip->addFile($fullPath, $uniqueFileName);
                                    }
                                }
                                $zip->close();

                                return response()->download($zipFilePath)->deleteFileAfterSend(true);
                            }

                            return redirect()->back()->with('error', 'Errore nella creazione del file ZIP.');
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListAttachments::route('/'),
            // 'create' => Pages\CreateAttachment::route('/create'),
            // 'edit' => Pages\EditAttachment::route('/{record}/edit'),
            // 'view' => Pages\ViewAttachment::route('/{record}'),
        ];
    }
}
