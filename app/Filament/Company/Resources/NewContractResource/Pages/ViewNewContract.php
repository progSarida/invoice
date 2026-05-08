<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use App\Filament\Company\Resources\NewContractResource;
use App\Models\NewContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class ViewNewContract extends ViewRecord
{
    protected static string $resource = NewContractResource::class;

    protected function getHeaderActions(): array
    {
        $currentContract = $this->record;
        // Precedente per start_validity_date: data precedente O stessa data con ID minore
        $previousDContract = NewContract::whereNotNull('start_validity_date')
            ->when($currentContract->start_validity_date, function ($query, $date) use ($currentContract) {
                return $query->where(function ($q) use ($date, $currentContract) {
                    $q->where('start_validity_date', '<', $date)
                        ->orWhere(function ($subQ) use ($date, $currentContract) {
                            $subQ->where('start_validity_date', '=', $date)
                                ->where('id', '<', $currentContract->id);
                        });
                });
            })
            ->orderBy('start_validity_date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per start_validity_date: data successiva O stessa data con ID maggiore
        $nextDContract = NewContract::whereNotNull('start_validity_date')
            ->when($currentContract->start_validity_date, function ($query, $date) use ($currentContract) {
                return $query->where(function ($q) use ($date, $currentContract) {
                    $q->where('start_validity_date', '>', $date)
                        ->orWhere(function ($subQ) use ($date, $currentContract) {
                            $subQ->where('start_validity_date', '=', $date)
                                ->where('id', '>', $currentContract->id);
                        });
                });
            })
            ->orderBy('start_validity_date', 'asc')->orderBy('id', 'asc')->first();
        // Precedente per ID: semplicemente ID minore
        $previousIContract = NewContract::where('id', '<', $currentContract->id)->orderBy('id', 'desc')->first();
        // Successivo per ID: semplicemente ID maggiore
        $nextIContract = NewContract::where('id', '>', $currentContract->id)->orderBy('id', 'asc')->first();

        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento data
            Actions\Action::make('previous_d_doc')
                ->label('Inizio prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDContract) { return $previousDContract;})
                ->action(function () use ($previousDContract) {
                    $this->redirect(NewContractResource::getUrl('view', ['record' => $previousDContract->id]));
                }),
            Actions\Action::make('next_d_doc')
                ->label('Inizio succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDContract) { return $nextDContract;})
                ->action(function () use ($nextDContract) {
                    $this->redirect(NewContractResource::getUrl('view', ['record' => $nextDContract->id]));
                }),
            // Scorrimento id
            Actions\Action::make('previou_i_doc')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIContract) { return $previousIContract;})
                ->action(function () use ($previousIContract) {
                    $this->redirect(NewContractResource::getUrl('view', ['record' => $previousIContract->id]));
                }),
            Actions\Action::make('next_i_doc')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIContract) { return $nextIContract;})
                ->action(function () use ($nextIContract) {
                    $this->redirect(NewContractResource::getUrl('view', ['record' => $nextIContract->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('stampa_pdf')
                    ->label('Stampa')
                    ->icon('heroicon-o-printer')
                    ->color(Color::rgb('rgb(255, 0, 0)'))
                    ->modalWidth('md')
                    ->form([
                        Checkbox::make('include_contract')
                            ->label('Allegare copia contratto originale')
                            ->helperText('Se selezionato, verrà allegato il PDF del contratto originale')
                            ->default(true)
                            // ->visible(fn (NewContract $record): bool =>
                            //     !empty($record->new_contract_copy_path) &&
                            //     file_exists(storage_path('app/public/' . $record->new_contract_copy_path))
                            // ),
                            ->visible(function (NewContract $record): bool {
                                    if (empty($record->new_contract_copy_path)) {
                                        return false;
                                    }
                                    // Recupero il disco configurato per i file (es. 's3' in prod, 'public' in locale)
                                    $disk = config('filesystems.default');

                                    return Storage::disk($disk)->exists($record->new_contract_copy_path);
                                }),
                    ])
                    ->action(function (NewContract $record, array $data) {

                        $includeContract = empty($data) ? false : true;

                        // Crea la directory temp se non esiste
                        $tempDir = storage_path('app/temp');
                        if (!file_exists($tempDir)) {
                            mkdir($tempDir, 0755, true); // Crea la directory con permessi 0755
                        }

                        // Verifica che la directory sia scrivibile
                        if (!is_writable($tempDir)) {
                            throw new \Exception('La directory ' . $tempDir . ' non è scrivibile.');
                        }

                        // Genera il primo PDF con DomPDF
                        $pdf = Pdf::loadView('pdf.contract', [
                            'contract' => $record,
                        ]);
                        $pdf->setPaper('A4', 'portrait');
                        $pdf->setOptions(['margin-top' => 0]);

                        // Salva temporaneamente il primo PDF
                        $tempPath = storage_path('app/temp/contract_temp.pdf');
                        try {
                            $pdf->save($tempPath);
                        } catch (\Exception $e) {
                            throw new \Exception('Errore durante il salvataggio del PDF temporaneo: ' . $e->getMessage());
                        }

                        // Inizializza FPDI per il merge
                        $fpdi = new Fpdi();

                        // Aggiungi le pagine del primo PDF (quello generato)
                        try {
                            $pageCount = $fpdi->setSourceFile($tempPath);
                            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                                $fpdi->AddPage();
                                $template = $fpdi->importPage($pageNo);
                                $fpdi->useTemplate($template);
                            }
                        } catch (\Exception $e) {
                            throw new \Exception('Errore durante l\'elaborazione del primo PDF: ' . $e->getMessage());
                        }

                        // Se l'opzione include_contract è selezionata e il file esiste, unisci il secondo PDF
                        if ($includeContract && !empty($record->new_contract_copy_path) && file_exists(storage_path('app/public/' . $record->new_contract_copy_path))) {
                            $secondPdfPath = storage_path('app/public/' . $record->new_contract_copy_path);
                            try {
                                $pageCount = $fpdi->setSourceFile($secondPdfPath);
                                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                                    $fpdi->AddPage();
                                    $template = $fpdi->importPage($pageNo);
                                    $fpdi->useTemplate($template);
                                }
                            } catch (\Exception $e) {
                                throw new \Exception('Errore durante l\'elaborazione del secondo PDF: ' . $e->getMessage());
                            }
                        }

                        // Elimina il file temporaneo
                        Storage::delete('temp/contract_temp.pdf');

                        // Genera il nome del file finale
                        // $fileName = 'contratto-' . $record->client->denomination . '-' . $record->tax_type->getLabel() . '-' . $record->lastDetail->number . '-' . $record->lastDetail->date->format('d-m-Y') . '.pdf';
                        $fileName = 'contratto-' . $record->client->denomination . '-' . implode('-', $record->tax_types) . '-' . $record->lastDetail->number . '-' . $record->lastDetail->date->format('d-m-Y') . '.pdf';

                        // Stream del PDF unito
                        return response()->streamDownload(function () use ($fpdi) {
                            echo $fpdi->Output('S');
                        }, $fileName);
                    }),
                Actions\EditAction::make(),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button()
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
