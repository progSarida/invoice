<?php

namespace App\Filament\Company\Resources;

use App\Enums\AttachmentType;
use App\Filament\Company\Resources\AttachmentResource\Pages;
use App\Filament\Company\Resources\AttachmentResource\RelationManagers;
use App\Models\Attachment;
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

    protected static ?string $navigationIcon = 'tni-attachment-o';

    // protected static ?string $navigationGroup = 'Gestione';

    // protected static ?int $navigationSort = 3;

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
                    ->label('Contratto')
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
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                    )
                    ->searchable()
                    ->preload()
                    ->optionsLimit(5),
                SelectFilter::make('notify_type')
                    ->label('Tipo allegato')
                    ->options(AttachmentType::class)
                    ->searchable()
                    ->preload(),
                Filter::make('interval_date')
                    ->label('Intervallo di date')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data inizio')
                            ->placeholder('Seleziona data inizio')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data fine')
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
            ->actions([
                // Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica file')
                    ->icon('tabler-file-download')
                    ->iconSize('lg')
                    ->action(function ($record) {
                        $filePath = $record->attachment_path;
                        // dd($filePath);
                        if ($filePath && Storage::disk('public')->exists($filePath)) {
                            $filename = explode("/", $filePath)[1];
                            return response()->download(
                                Storage::disk('public')->path($filePath),
                                // 'document_' . $record->number . '.pdf'
                                $filename
                            );
                        }
                        return redirect()->back()->with('error', 'File PDF non trovato.');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('download_all')
                        ->label('Scarica selezionati')
                        ->icon('tabler-download')
                        ->action(function ($records) {
                            $zip = new \ZipArchive();
                            $zipFileName = 'allegati_' . now()->format('Y-m-d_His') . '.zip';
                            $zipFilePath = storage_path('app/public/' . $zipFileName);

                            if ($zip->open($zipFilePath, \ZipArchive::CREATE) === TRUE) {
                                foreach ($records as $record) {
                                    $filePath = $record->attachment_path;
                                    if ($filePath && Storage::disk('public')->exists($filePath)) {
                                        $fullPath = Storage::disk('public')->path($filePath);
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
        ];
    }
}
