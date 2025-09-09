<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use App\Filament\Company\Resources\NewContractResource;
use App\Filament\Exports\NewContractExporter;
use App\Models\Container;
use App\Models\Contract;
use App\Models\ContractDetail;
use App\Models\NewContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\MaxWidth;



class ListNewContracts extends ListRecords
{
    protected static string $resource = NewContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            
            Actions\Action::make('convert')
                ->label('Converti vecchi')
                ->visible(fn (): bool => Auth::user()->is_admin)
                ->tooltip('Converti vecchi contratti')
                ->color('primary')
                ->action(function ($livewire) {
                    try {
                        $tenant = Filament::getTenant();

                        DB::beginTransaction();

                        $containers = Container::with('tender')
                            ->where('company_id', $tenant->id)
                            // ->where('id', 12)
                            // ->limit(5)
                            ->get();

                        foreach($containers as $container){
                            $accrualTypesRefs = $container->accrual_types;
                            $accrualTypesIds = [];

                            if (!empty($accrualTypesRefs)) {
                                // Mappa le label ai valori ref
                                $refMapping = [
                                    'Competenza ordinaria' => 'ordinary',
                                    'Competenza coattiva' => 'coercive', 
                                    'Accertamenti' => 'verification',
                                    'Servizi' => 'service'
                                ];
                                
                                $mappedRefs = array_map(function($label) use ($refMapping) {
                                    return $refMapping[$label] ?? $label;
                                }, $accrualTypesRefs);
                                
                                $accrualTypesIds = \App\Models\AccrualType::whereIn('ref', $mappedRefs)
                                    ->pluck('id')
                                    ->toArray();
                            }

                            $dataC = [
                                'client_id'                 => $container->client_id,
                                'company_id'                => $tenant->id,
                                'tax_types'                 => array_map(function($value) { 
                                    return strtolower(trim($value)); 
                                }, $container->tax_types), // Anche questo sarà già un array
                                'end_validity_date'         => null,
                                'accrual_types'             => $accrualTypesIds,
                                'payment_type'              => $container->tender->type,
                                'reinvoice'                 => 0,
                                'cig_code'                  => $container->tender->cig_code ?? '',
                                'cup_code'                  => $container->tender->cup_code ?? '',
                                'office_code'               => $container->tender->office_code ?? '',
                                'office_name'               => $container->tender->office_name ?? '',
                                'amount'                    => 0,
                                'invoicing_cycle'           => null,
                                'new_contract_copy_path'    => null,
                                'new_contract_copy_date'    => null
                            ];

                            // dd($dataC);

                            $newContract = NewContract::create($dataC);

                            $details = Contract::where('contracts.container_id', $container->id)               // seleziono solo i dati del tenant corrente
                                ->select(
                                    'contracts.number',
                                    'contracts.type as contract_type',
                                    'contracts.contract_date as date'
                                )
                                ->get();

                            foreach($details as $detail){
                                $dataD = [
                                    'company_id'        => $tenant->id,
                                    'contract_id'       => $newContract->id,
                                    'number'            => $detail->number,
                                    'contract_type'     => $detail->contract_type,
                                    'date'              => $detail->date
                                ];

                                $contractDetail  = ContractDetail::create($dataD);
                            }

                        }

                        DB::commit();

                        // dd('STOP');

                        Notification::make()
                            ->title('Conversione completata')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }),
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                // ->keyBindings(['alt+n'])
                ,
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco contratti')
                ->color('primary')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get();                           // Recupero risultato della query
                    $filters = $livewire->tableFilters ?? [];                                       // Recupero i filtri
                    $search = $livewire->tableSearch ?? null;                                       // Recupero la ricerca

                    $fileName = 'Contratti_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            echo Pdf::loadHTML(
                                Blade::render('pdf.new_contracts', [
                                    'contracts' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                                ->setPaper('A4', 'landscape')
                                ->stream();
                        }, $fileName);

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                })
                // ->keyBindings(['alt+s'])
                ,
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->color('primary')
                ->exporter(NewContractExporter::class)
                // ->keyBindings(['alt+e'])
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
