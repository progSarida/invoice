<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Company\Resources\NewContractResource;
use App\Models\NewContract;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class EditNewContract extends EditRecord
{
    protected static string $resource = NewContractResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('stampa_pdf')
                ->label('Stampa')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->modalWidth('md')
                ->form([
                    Checkbox::make('include_contract')
                        ->label('Allegare copia contratto originale')
                        ->helperText('Se selezionato, verrà allegato il PDF del contratto originale')
                        ->default(true)
                        ->visible(fn (NewContract $record): bool =>
                            !empty($record->new_contract_copy_path) &&
                            file_exists(storage_path('app/public/' . $record->new_contract_copy_path))
                        ),
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
                    $fileName = 'contratto-' . $record->client->denomination . '-' . $record->tax_type->getLabel() . '-' . $record->lastDetail->number . '-' . $record->lastDetail->date->format('d-m-Y') . '.pdf';

                    // Stream del PDF unito
                    return response()->streamDownload(function () use ($fpdi) {
                        echo $fpdi->Output('S');
                    }, $fileName);
                }),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => Auth::user()->isManagerOf(\Filament\Facades\Filament::getTenant())),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function beforeSave(): void
    {
        if (!is_numeric($this->form->getState()['amount'])) {
            Notification::make()
                ->title('Importo non valido')
                ->danger()
                ->send();

            $this->halt(); // blocca il salvataggio
        }
    }
}
