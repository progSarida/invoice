<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use App\Filament\Company\Resources\NewContractResource;
use App\Filament\Exports\NewContractExporter;
use App\Models\Container;
use App\Models\Contract;
use App\Models\ContractDetail;
use App\Models\Invoice;
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

            // Actions\Action::make('convert')
            //     ->label('Converti vecchi**')
            //     ->visible(fn (): bool => Auth::user()->is_admin)
            //     ->tooltip('Converti vecchi contratti')
            //     ->color('primary')
            //     ->action(function ($livewire) {
            //         try {
            //             $tenant = Filament::getTenant();
            //             $containerToContractMap = [];

            //             DB::beginTransaction();

            //             $containers = Container::with('tender')
            //                 ->where('company_id', $tenant->id)
            //                 // ->where('id', 12)                                                                   // per test
            //                 // ->whereBetween('id', [15, 20])                                                      // per test
            //                 // ->limit(5)                                                                          // per test
            //                 // ->get();                                                                            // per test
            //                 ->lazy(500);                                                                        //

            //             foreach($containers as $container){
            //                 $accrualTypesRefs = $container->accrual_types;
            //                 $accrualTypesIds = [];

            //                 if (!empty($accrualTypesRefs)) {

            //                     $refMapping = [                                                                 // mappo le label ai valori ref
            //                         'Competenza ordinaria' => 'ordinary',
            //                         'Competenza coattiva' => 'coercive',
            //                         'Accertamenti' => 'verification',
            //                         'Servizi' => 'service'
            //                     ];

            //                     $mappedRefs = array_map(function($label) use ($refMapping) {
            //                         return $refMapping[$label] ?? $label;
            //                     }, $accrualTypesRefs);

            //                     $accrualTypesIds = \App\Models\AccrualType::whereIn('ref', $mappedRefs)
            //                         ->pluck('id')
            //                         ->toArray();
            //                 }

            //                 $dataC = [
            //                     'client_id'                 => $container->client_id,
            //                     'company_id'                => $tenant->id,
            //                     'tax_types'                 => array_map(function($value) {
            //                                                         return strtolower(trim($value));
            //                                                     }, $container->tax_types),
            //                     'end_validity_date'         => null,
            //                     'accrual_types'             => $accrualTypesIds,
            //                     'payment_type'              => $container->tender->type,
            //                     'reinvoice'                 => false,
            //                     'cig_code'                  => $container->tender->cig_code ?? '',
            //                     'cup_code'                  => $container->tender->cup_code ?? '',
            //                     'office_code'               => $container->tender->office_code ?? '',
            //                     'office_name'               => $container->tender->office_name ?? '',
            //                     'amount'                    => 0,
            //                     'invoicing_cycle'           => null,
            //                     'new_contract_copy_path'    => null,
            //                     'new_contract_copy_date'    => null
            //                 ];

            //                 // dd($dataC);

            //                 $newContract = NewContract::create($dataC);
            //                 $containerToContractMap[$container->id] = $newContract->id;                         // salvo l'id del nuovo contratto associandolo all'id del relativo container

            //                 $details = Contract::where('contracts.container_id', $container->id)                // seleziono solo i dati del tenant corrente
            //                     ->select(
            //                         'contracts.number',
            //                         'contracts.type as contract_type',
            //                         'contracts.contract_date as date'
            //                     )
            //                     ->get();

            //                 foreach($details as $detail){
            //                     $dataD = [
            //                         // 'company_id'        => $tenant->id,
            //                         'contract_id'       => $newContract->id,
            //                         'number'            => $detail->number,
            //                         'contract_type'     => $detail->contract_type,
            //                         'date'              => $detail->date
            //                     ];

            //                     $contractDetail  = ContractDetail::create($dataD);
            //                 }

            //             }

            //             // dd($containerToContractMap);                                                                        // associazione container-new_contract

            //             // Preparazione confronti per aggiornamento dati invoice
            //             $docTypeMapping = [                                                                                 // mappa per confronto invoice_type <-> doc_types.name
            //                 'invoice' => 'TD01',
            //                 'credit_note' => 'TD04',
            //                 'invoice_notice' => 'TD00'
            //             ];
            //             $docTypes = \App\Models\DocType::whereIn('name', array_values($docTypeMapping))->pluck('id', 'name')->toArray();            // tipi doc per confronto su name

            //             $accrualRefMapping = [                                                                              // mappa per confronto accrual_type <-> accrual_types.ref
            //                 'Competenza ordinaria' => 'ordinary',
            //                 'Competenza coattiva' => 'coercive',
            //                 'Accertamenti' => 'verification',
            //                 'Servizi' => 'service'
            //             ];
            //             $accrualTypes = \App\Models\AccrualType::whereIn('ref', array_values($accrualRefMapping))->pluck('id', 'ref')->toArray();   // tipi competenza per confronto su ref

            //             $sectionals = \App\Models\Sectional::where('company_id', $tenant->id)->pluck('id', 'description')->toArray();               // sezionari per confronto su description

            //             $invoices = Invoice::where('company_id', $tenant->id)->where('flow', null)->lazy(500);              // seleziono invoice vecchi del tenant
            //             // $invoices = Invoice::where('company_id', $tenant->id)->where('flow', null)->get();
            //             // $invoiceTypes = $invoices->pluck('invoice_type');
            //             // dd($invoiceTypes);
            //             foreach($invoices as $invoice) {
            //                 // dd($invoice);
            //                 $contractId = $containerToContractMap[$invoice->container_id] ?? null;
            //                 if (!$contractId) { continue; }

            //                 $contractDetailId = \App\Models\ContractDetail::where('contract_id', $contractId)               // trovo il contract_detail_id più recente con stesso tax_type
            //                     ->whereHas('contract', function($query) use ($invoice) {
            //                         $query->whereJsonContains('tax_types', strtolower(trim($invoice->tax_type->value)));
            //                     })
            //                     ->orderBy('date', 'desc')
            //                     ->value('id');

            //                 $docTypeName = $docTypeMapping[$invoice->invoice_type->value] ?? null;
            //                 $docTypeId = $docTypeName ? ($docTypes[$docTypeName] ?? null) : null;                           // determino il doc_type_id da invoice_type

            //                 $sectionPadded = str_pad($invoice->section, 2, '0', STR_PAD_LEFT);
            //                 $sectionalId = $sectionals[$sectionPadded] ?? null;                                             // determino il sectional_id da section

            //                 $accrualRef = $accrualRefMapping[$invoice->accrual_type] ?? $invoice->accrual_type;             // determino il accrual_type_id da accrual_type
            //                 $accrualTypeId = $accrualTypes[$accrualRef] ?? null;

            //                 $dataI = [
            //                     'contract_id' => $contractId,
            //                     'contract_detail_id' => $contractDetailId,
            //                     'doc_type_id' => $docTypeId,
            //                     'year_limit' => null,
            //                     'limit_motivation_type_id' => null,
            //                     'timing_type' => 'contestuale',
            //                     'delivery_note' => null,
            //                     'delivery_date' => null,
            //                     'art_73' => 0,
            //                     'social_contributions' => [],
            //                     'withholdings' => [],
            //                     'vat_enforce_type' => null,
            //                     'sectional_id' => $sectionalId,
            //                     'accrual_type_id' => $accrualTypeId,
            //                     'manage_type_id' => null,                                                                   // era in Fat_SubjectTypeId (non in tutti), non presente
            //                     'invoice_reference' => null,
            //                     'reference_date_from' => null,
            //                     'reference_date_to' => null,
            //                     'reference_number_from' => null,
            //                     'reference_number_to' => null,
            //                     'total_number' => null,
            //                     'payment_mode' => 'tp02',
            //                     'rate_number' => 1,
            //                     'total_notes' => '',
            //                     'service_code' => null
            //                 ];

            //                 // dd($dataI);

            //                 $invoice->update($dataI);
            //             }

            //             DB::commit();

            //             // dd('STOP');

            //             Notification::make()
            //                 ->title('Conversione completata')
            //                 ->success()
            //                 ->send();
            //         } catch (\Exception $e) {
            //             DB::rollBack();
            //             throw $e;
            //         }
            //     }),
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
