<?php

namespace App\Services;

use App\Enums\ClientSubType;
use App\Enums\FundType;
use DateTime;
use Exception;
use Illuminate\Support\Str;
use SoapFault;
use SoapClient;
use Carbon\Carbon;
use App\Models\State;
use App\Models\Company;
use App\Models\Invoice;
use App\Enums\SdiStatus;
use App\Enums\WithholdingType;
use App\Models\Client;
use App\Models\Deadline;
use App\Models\PassiveDownload;
use App\Models\PassiveInvoice;
use App\Models\PassiveItem;
use App\Models\SdiNotification;
use App\Models\SdiRequest;
use App\Models\Supplier;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class AndxorSoapService
{
    protected $client;

    public function __construct()
    {
        $wsdl = config('services.andxor.wsdl_url');

        $options = [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => \WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
        ];

        try {
            $this->client = new SoapClient($wsdl, $options);
        } catch (Exception $e) {
            throw new Exception('Errore nella connessione al servizio SOAP: ' . $e->getMessage());
        }
    }

    private function validateCodiceDestinatario(?string $codice): string
    {
        if (empty($codice) || !preg_match('/^[A-Z0-9]{6,7}$/', $codice)) {
            throw new Exception("CodiceDestinatario non valido: $codice.");
            // Log::warning("CodiceDestinatario non valido: $codice. Usato valore predefinito '0000000'.");
            // return '0000000';
        }
        return $codice;
    }

    private function mapPaymentTypeToCondizioniPagamento(string $paymentType): string
    {
        $mapping = [
            'MP01' => 'TP02',
            'MP05' => 'TP02',
            'MP08' => 'TP02',
        ];
        return $mapping[$paymentType] ?? 'TP02';
    }

    private function validateIdFiscaleIVACompany(Company $company, string $idPaese): ?array
    {
        $idCodice = $company->vat_number ?? $company->tax_number;
        if ($idCodice && preg_match('/^[A-Za-z0-9]{1,28}$/', $idCodice)) {
            return [
                'IdPaese' => $idPaese,
                'IdCodice' => $idCodice,
            ];
        }
        return null;
    }

    private function validateIdFiscaleIVAClient(Client $client, string $idPaese): ?array
    {
        if($client->subtype == ClientSubType::MAN || $client->subtype == ClientSubType::WOMAN) {
            return null;
        }
        $idCodice = $client->vat_code ?? $client->tax_code;
        if ($idCodice && preg_match('/^[A-Za-z0-9]{1,28}$/', $idCodice)) {
            return [
                'IdPaese' => $idPaese,
                'IdCodice' => $idCodice,
            ];
        }
        return null;
    }

    private function validateRegimeFiscale(?string $regime): string
    {
        $validRegimes = [
            'RF01', 'RF02', 'RF03', 'RF04', 'RF05', 'RF06', 'RF07', 'RF08', 'RF09', 'RF10',
            'RF11', 'RF12', 'RF13', 'RF14', 'RF15', 'RF16', 'RF17', 'RF18', 'RF19'
        ];
        if ($regime && preg_match('/^[A-Za-z0-9]{1,20}$/', $regime) && in_array($regime, $validRegimes)) {
            return $regime;
        }
        Log::warning("RegimeFiscale non valido: '$regime'. Usato valore predefinito 'RF01'.");
        return 'RF01';
    }

    private function getAutenticazione(?Invoice $invoice, string $password): ?array
    {
        $entity = $invoice ? $invoice->company : Filament::getTenant();

        $state = State::find($entity->state_id);
        $alpha2 = ($state && preg_match('/^[A-Z]{2}$/', $state->alpha2)) ? $state->alpha2 : 'IT';

        $idCodice = $entity->vat_number ?? $entity->taxnumber;

        if ($alpha2 && preg_match('/^[A-Za-z0-9]{1,28}$/', $idCodice)) {
            return [
                'Cedente' => [
                    'IdPaese' => $alpha2,
                    'IdCodice' => $idCodice,
                ],
                'Password' => $password,
            ];
        }

        return null;
    }

    private function getOverrideCedente(Invoice $invoice): ?array
    {
        $idPaeseCedente = $invoice->company->state_id && State::find($invoice->company->state_id) && preg_match('/^[A-Z]{2}$/', State::find($invoice->company->state_id)->alpha2) ? State::find($invoice->company->state_id)->alpha2 : 'IT';
        return [
            'DatiAnagrafici' => array_filter([
                'IdFiscaleIVA' => $this->validateIdFiscaleIVACompany($invoice->company, $idPaeseCedente),
                'CodiceFiscale' => $invoice->company->tax_number && preg_match('/^[A-Z0-9]{11,16}$/', $invoice->company->tax_number) ? $invoice->company->tax_number : null,
                'Anagrafica' => [
                    'Denominazione' => $invoice->company->name,
                ],
                'RegimeFiscale' => $this->validateRegimeFiscale($invoice->company->fiscalProfile->tax_regime->getCode() ?? 'RF01'),
            ], fn($value) => !is_null($value) && $value !== ''),
            'Sede' => array_filter([
                'Indirizzo' => $invoice->company->address ?? '',
                'NumeroCivico' => $invoice->company->address_number && preg_match('/^[A-Za-z0-9]{1,8}$/', $invoice->company->address_number) ? $invoice->company->address_number : null,
                'CAP' => $invoice->company->city->zip_code ?? '',
                'Comune' => $invoice->company->city->name ?? '',
                'Provincia' => $invoice->company->city->province->code ?? '',
                'Nazione' => $idPaeseCedente,
            ], fn($value) => !is_null($value) && $value !== ''),
            'Contatti' => array_filter([
                'Telefono' => $invoice->company->phone && preg_match('/^[A-Za-z0-9]{5,12}$/', $invoice->company->phone) ? $invoice->company->phone : null,
                'Email' => $invoice->company->email && preg_match('/^.+@.+[.]+.+$/', $invoice->company->email) ? $invoice->company->email : null,
            ], fn($value) => !is_null($value) && $value !== '') ?: null,
        ];
    }

    private function getCessionarioCommittente(Invoice $invoice): ?array
    {
        $idPaeseCommittente = $invoice->client->state_id && State::find($invoice->client->state_id) && preg_match('/^[A-Z]{2}$/', State::find($invoice->client->state_id)->alpha2) ? State::find($invoice->client->state_id)->alpha2 : 'IT';
        return [
            'DatiAnagrafici' => [
                'IdFiscaleIVA' => $this->validateIdFiscaleIVAClient($invoice->client, $idPaeseCommittente),
                'CodiceFiscale' => $invoice->client->tax_code ?? null,
                'Anagrafica' => [
                    'Denominazione' => $invoice->client->denomination,
                ],
            ],
            'Sede' => array_filter([
                'Indirizzo' => $invoice->client->address ?? '',
                'NumeroCivico' => $invoice->client->address_number && preg_match('/^[A-Za-z0-9]{1,8}$/', $invoice->client->address_number) ? $invoice->client->address_number : null,
                'CAP' => $invoice->client->zip_code ?? '',
                'Comune' => $invoice->client->city->name ?? '',
                'Provincia' => $invoice->client->city->province->code ?? '',
                'Nazione' => $idPaeseCommittente,
            ], fn($value) => !is_null($value) && $value !== ''),
        ];
    }

    private function getDatiGeneraliDocumento(Invoice $invoice, array $withholdings, array $funds): ?array
    {
        $out = [
                'TipoDocumento' => $invoice->docType->name && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->docType->name) ? $invoice->docType->name : 'TD01',
                'Divisa' => $invoice->divisa ?? 'EUR',
                'Data' => $invoice->invoice_date->format('Y-m-d'),
                'Numero' => $invoice->getNewInvoiceNumber(),
                'ImportoTotaleDocumento' => sprintf("%.2f", (float) ($invoice->total ?? 0.00)),
                // 'Causale' => $invoice->description ?? '',
                'Causale' => substr($invoice->description, 0, 200) ?? '',
                'DatiRitenuta' => array_map(function ($withholding) {
                    return [
                        'TipoRitenuta' => $withholding['tipo_ritenuta'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['tipo_ritenuta']) ? $withholding['tipo_ritenuta'] : 'RT01',
                        'ImportoRitenuta' => sprintf("%.2f", (float) ($withholding['importo_ritenuta'] ?? 0.00)),
                        'AliquotaRitenuta' => sprintf("%.2f", (float) ($withholding['aliquota_ritenuta'] ?? 20.00)),
                        'CausalePagamento' => $withholding['causale_pagamento'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['causale_pagamento']) ? $withholding['causale_pagamento'] : 'A',
                    ];
                }, $withholdings)
            ];

        if ($invoice->virtualStamp()) {
            $out['DatiBollo'] = [
                'BolloVirtuale' => 'SI',
                'ImportoBollo' => $invoice->company->stampDuty->virtual_stamp ? sprintf("%.2f", 2.00) : '',
            ];
        }

        if (!empty($funds)) {
            $out['DatiCassaPrevidenziale'] = array_map(function ($fund) {
                return array_filter([
                    'TipoCassa' => $fund['fund_code'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $fund['fund_code']) ? $fund['fund_code'] : 'TC02',
                    'AlCassa' => sprintf("%.2f", (float) ($fund['rate'])),
                    'ImportoContributoCassa' => sprintf("%.2f", (float) ($fund['amount'])),
                    'ImponibileCassa' => sprintf("%.2f", (float) ($fund['taxable_base'])),
                    'AliquotaIVA' => isset($fund['%']) && $fund['%'] !== null ? sprintf("%.2f", (float) $fund['%']) : "0.00",
                    'Ritenuta' => !empty($fund['withholding']) ? 'SI' : null,
                    'Natura' => $fund['%'] == 'N1' ? 'N1' : null,
                ], fn($value) => !is_null($value) && $value !== '');
            }, $funds);
        }

        return $out;
    }

    private function getDatiOrdineAcquisto(Invoice $invoice): ?array
    {
        if(strpos($invoice->contract?->cig_code, '#') === false){
            $cig = $invoice->contract?->cig_code;
        }
        else{
            $cig = '';
        }
        return $invoice->contract ? array_filter([
            array_filter([
                'IdDocumento' => $invoice->contract->lastDetail->number && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->contract->lastDetail->number) ? $invoice->contract->lastDetail->number : null,
                'Data' => $invoice->contract->lastDetail->date ?? null,
                'CodiceCUP' => $invoice->contract?->cup_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract?->cup_code) ? $invoice->contract?->cup_code : null,
                'CodiceCIG' => $cig && preg_match('/^[A-Za-z0-9]{1,15}$/', $cig) ? $cig : null,
            ], fn($value) => !is_null($value) && $value !== '')
        ], fn($value) => !empty($value)) : [];
    }

    private function getDatiContratto(Invoice $invoice): ?array
    {
        if(strpos($invoice->contract?->cig_code, '#') === false){
            $cig = $invoice->contract?->cig_code;
        }
        else{
            $cig = '';
        }
        return $invoice->contract ? array_filter([
            array_filter([
                'IdDocumento' => $invoice->contract?->lastDetail?->number && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->contract?->lastDetail?->number) ? $invoice->contract?->lastDetail?->number : null,
                'Data' => $invoice->contract?->lastDetail?->date ?? null,
                'CodiceCUP' => $invoice->contract?->cup_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract?->cup_code) ? $invoice->contract?->cup_code : null,
                'CodiceCIG' => $cig && preg_match('/^[A-Za-z0-9]{1,15}$/', $cig) ? $cig : null,
            ], fn($value) => !is_null($value) && $value !== '')
        ], fn($value) => !empty($value)) : [];
    }

    private function getDatiDDT(Invoice $invoice): ?array
    {
        return $invoice->delivery_note ? [
            [
                'NumeroDDT' => $invoice->delivery_note,
                'DataDDT' => $invoice->delivery_date ?? $invoice->invoice_date->format('Y-m-d'),
            ],
        ] : [];
    }

    private function getDatiBeniServizi(Invoice $invoice): ?array
    {
        return [
            'DettaglioLinee' => $invoice->invoiceItems->where('auto', false)->map(function ($item, $index) {
                return [
                    'NumeroLinea' => $index + 1,
                    'Descrizione' => $item->description ?? 'Servizio',
                    'Quantita' => sprintf("%.2f", (float) ($item->quantity ?? 1.00)),
                    'UnitaMisura ' => $item->measure_unit ?? null,
                    'PrezzoUnitario' => sprintf("%.2f", (float) ($item->unit_price ?? $item->amount)),
                    'PrezzoTotale' => sprintf("%.2f", (float) $item->amount),
                    'AliquotaIVA' => is_numeric($item->vat_code_type->getRate()) ? sprintf("%.2f", (float) $item->vat_code_type->getRate()) : "0.00",
                    'Natura' => $item->vat_code_type->getRate() == '0' ? $item->vat_code_type->getCode() : null,
                ];
            })->toArray(),
            'DatiRiepilogo' => array_values(array_map(function ($vat) {
                return [
                    'AliquotaIVA' => isset($vat['%']) && $vat['%'] !== 'N1' ? sprintf("%.2f", (float) $vat['%']) : "0.00",
                    'Natura' => $vat['%'] == 'N1' ? 'N1' : null,
                    'ImponibileImporto' => sprintf("%.2f", (float) $vat['taxable']),
                    'Imposta' => sprintf("%.2f", (float) $vat['vat']),
                    'EsigibilitaIVA' => in_array($vat['norm'][0], ['D', 'I', 'S']) ? $vat['norm'][0] : null,
                    'RiferimentoNormativo' => $vat['free'] ? $vat['norm'] : null,
                ];
            }, $invoice->updateResume($invoice->vatResume(), $invoice->getFundBreakdown()))),
        ];
    }

    private function getDatiPagamento(Invoice $invoice): ?array
    {
        $total = $invoice->client->type->value == 'public' ? $invoice->no_vat_total : $invoice->total ;
        return [
            [
                'CondizioniPagamento' => $this->mapPaymentTypeToCondizioniPagamento($invoice->payment_type->value ?? 'TP02'),
                'DettaglioPagamento' => [
                    [
                        'ModalitaPagamento' => $invoice->payment_type->getCode() ?? 'MP05',
                        'DataScadenzaPagamento' => $invoice->invoice_date->addDays($invoice->payment_days ?? 30)->format('Y-m-d'),
                        'ImportoPagamento' => sprintf("%.2f", (float) ($total ?? 0.00)),
                        'IBAN' => $invoice->bankAccount->iban ?? null,
                    ],
                ],
            ],
        ];
    }

    private function translateStatus(string $status): string
    {
        $translated = "";
        switch($status){
            case  'Generata':
                $translated = "generata";
                break;
            case  'Trasmessa allo SdI':
                $translated = "trasmessa_sdi";
                break;
            case  'Scartata':
                $translated = "scartata";
                break;
            case  'Non ancora consegnata':
                $translated = "non_consegnata";
                break;
            case  'Consegnata':
                $translated = "consegnata";
                break;
            case  'Accettata':
                $translated = "accettata";
                break;
            case  'Rifiutata':
                $translated = "rifiutata";
                break;
            case  'Decorsi i termini':
                $translated = "decorrenza_termini";
                break;
            case  'Non recapitabile':
                $translated = "non_recapitabile";
                break;
            case  'Nel cassetto':
                $translated = "nel_cassetto";
                break;
            case  'Rielaborata':
                $translated = "rielaborata";
                break;
            case  'Importata':
                $translated = "importata";
                break;
        }
        return $translated;
    }

    public function sendInvoice(Invoice $invoice, string $password)
    {
        try {
            $vats = $invoice->vatResume();
            $funds = array_filter($invoice->getFundBreakdown(), function ($fund) {
                return isset($fund['fund_code'], $fund['rate'], $fund['amount'], $fund['taxable_base']);
            });
            if (count($funds) > 0) {
                $vats = $invoice->updateResume($vats, $funds);
            }
            $withholdings = array_filter($invoice->company->withholdings->toArray(), function ($item) {
                return in_array($item['withholding_type'], [WithholdingType::RT01, WithholdingType::RT02])
                    && isset($item['tipo_ritenuta'], $item['importo_ritenuta'], $item['aliquota_ritenuta'], $item['causale_pagamento']);
            });
            $idPaeseCedente = $invoice->company->state_id && State::find($invoice->company->state_id) && preg_match('/^[A-Z]{2}$/', State::find($invoice->company->state_id)->alpha2) ? State::find($invoice->company->state_id)->alpha2 : 'IT';
            $idPaeseCommittente = $invoice->client->state_id && State::find($invoice->client->state_id) && preg_match('/^[A-Z]{2}$/', State::find($invoice->client->state_id)->alpha2) ? State::find($invoice->client->state_id)->alpha2 : 'IT';

            if (!$invoice->company->vat_number && !$invoice->company->tax_number) {
                Log::error('Dati fiscali mancanti per Cedente: né vat_number né tax_number forniti.');
                throw new Exception('Dati fiscali mancanti per Cedente.');
            }

            if (!$invoice->company->fiscalProfile || !$invoice->company->fiscalProfile->tax_regime) {
                Log::error('Dati fiscali mancanti per Cedente: fiscalProfile o tax_regime non definiti.');
                throw new Exception('Dati fiscali mancanti per Cedente: regime fiscale non definito.');
            }

            // Creazione array di input
            $payload['Autenticazione'] = $this->getAutenticazione($invoice, $password);
            $codiceDestinatario = $invoice->client->ipa_code ?? $invoice->contract->office_code ?? null;
            if($invoice->client->subtype == ClientSubType::MAN || $invoice->client->subtype == ClientSubType::WOMAN){
                $codiceDestinatario = '0000000';
            }
            if (!empty($codiceDestinatario)) {
                $payload['CodiceDestinatario'] = $this->validateCodiceDestinatario($codiceDestinatario);
            } else {
                $payload['PECDestinatario'] = $invoice->client->pec;
            }
// GESTIRE INVIO A PRIVATI SENZA CodiceDestinatario e PECDestinatario
            $payload['OverrideCedente'] = $this->getOverrideCedente($invoice);
            $payload['CessionarioCommittente'] = $this->getCessionarioCommittente($invoice);
            $payload['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento'] = $this->getDatiGeneraliDocumento($invoice, $withholdings, $funds);
            // $payload['FatturaElettronicaBody']['DatiGenerali']['DatiOrdineAcquisto'] = $this->getDatiOrdineAcquisto($invoice);
            $payload['FatturaElettronicaBody']['DatiGenerali']['DatiContratto'] = $this->getDatiContratto($invoice) == [] ? null : $this->getDatiContratto($invoice);
            $payload['FatturaElettronicaBody']['DatiGenerali']['DatiDDT'] = $this->getDatiDDT($invoice);
            $payload['FatturaElettronicaBody']['DatiBeniServizi'] = $this->getDatiBeniServizi($invoice);
            $payload['FatturaElettronicaBody']['DatiPagamento'] = $this->getDatiPagamento($invoice);

            // $payload_ = [
            //     'Autenticazione' => [
            //         'Cedente' => [
            //             'IdPaese' => $idPaeseCedente,
            //             'IdCodice' => $invoice->company->vat_number ?? $invoice->company->taxnumber,
            //             'IdCodice_' => '01338160995',
            //         ],
            //         'Password' => $password,
            //     ],
            //     'CodiceDestinatario' => $this->validateCodiceDestinatario($invoice->client->ipa_code ?? $invoice->contract->office_code ?? '0000000'),
            //     'PECDestinatario' => $invoice->client->pec,
            //     'OverrideCedente' => [
            //         'DatiAnagrafici' => array_filter([
            //             'IdFiscaleIVA' => $this->validateIdFiscaleIVA($invoice->company->vat_number, $invoice->company->tax_number, $idPaeseCedente),
            //             'CodiceFiscale' => $invoice->company->tax_number && preg_match('/^[A-Z0-9]{11,16}$/', $invoice->company->tax_number) ? $invoice->company->tax_number : null,
            //             'Anagrafica' => [
            //                 'Denominazione' => $invoice->company->name,
            //             ],
            //             'RegimeFiscale' => $this->validateRegimeFiscale($invoice->company->fiscalProfile->tax_regime->getCode() ?? 'RF01'),
            //         ], fn($value) => !is_null($value) && $value !== ''),
            //         'Sede' => array_filter([
            //             'Indirizzo' => $invoice->company->address ?? '',
            //             'NumeroCivico' => $invoice->company->address_number && preg_match('/^[A-Za-z0-9]{1,8}$/', $invoice->company->address_number) ? $invoice->company->address_number : null,
            //             'CAP' => $invoice->company->city->zip_code ?? '',
            //             'Comune' => $invoice->company->city->name ?? '',
            //             'Provincia' => $invoice->company->city->province->code ?? '',
            //             'Nazione' => $idPaeseCedente,
            //         ], fn($value) => !is_null($value) && $value !== ''),
            //         'Contatti' => array_filter([
            //             'Telefono' => $invoice->company->phone && preg_match('/^[A-Za-z0-9]{5,12}$/', $invoice->company->phone) ? $invoice->company->phone : null,
            //             'Email' => $invoice->company->email && preg_match('/^.+@.+[.]+.+$/', $invoice->company->email) ? $invoice->company->email : null,
            //         ], fn($value) => !is_null($value) && $value !== '') ?: null,
            //     ],
            //     'CessionarioCommittente' => [
            //         'DatiAnagrafici' => [
            //             'IdFiscaleIVA' => $this->validateIdFiscaleIVA($invoice->client->vat_code, $invoice->client->tax_code, $idPaeseCommittente),
            //             'CodiceFiscale' => $invoice->client->tax_code ?? null,
            //             'Anagrafica' => [
            //                 'Denominazione' => $invoice->client->denomination,
            //             ],
            //         ],
            //         'Sede' => array_filter([
            //             'Indirizzo' => $invoice->client->address ?? '',
            //             'NumeroCivico' => $invoice->client->address_number && preg_match('/^[A-Za-z0-9]{1,8}$/', $invoice->client->address_number) ? $invoice->client->address_number : null,
            //             'CAP' => $invoice->client->city->zip_code ?? '',
            //             'Comune' => $invoice->client->city->name ?? '',
            //             'Provincia' => $invoice->client->city->province->code ?? '',
            //             'Nazione' => $idPaeseCommittente,
            //         ], fn($value) => !is_null($value) && $value !== ''),
            //     ],
            //     'FatturaElettronicaBody' => [
            //         'DatiGenerali' => [
            //             'DatiGeneraliDocumento' => [
            //                 'TipoDocumento' => $invoice->docType->name && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->docType->name) ? $invoice->docType->name : 'TD01',
            //                 'Divisa' => $invoice->divisa ?? 'EUR',
            //                 'Data' => $invoice->invoice_date->format('Y-m-d'),
            //                 'Numero' => $invoice->getNewInvoiceNumber(),
            //                 'ImportoTotaleDocumento' => sprintf("%.2f", (float) ($invoice->total ?? 0.00)),
            //                 'DatiRitenuta' => array_map(function ($withholding) {
            //                     return [
            //                         'TipoRitenuta' => $withholding['tipo_ritenuta'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['tipo_ritenuta']) ? $withholding['tipo_ritenuta'] : 'RT01',
            //                         'ImportoRitenuta' => sprintf("%.2f", (float) ($withholding['importo_ritenuta'] ?? 0.00)),
            //                         'AliquotaRitenuta' => sprintf("%.2f", (float) ($withholding['aliquota_ritenuta'] ?? 20.00)),
            //                         'CausalePagamento' => $withholding['causale_pagamento'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['causale_pagamento']) ? $withholding['causale_pagamento'] : 'A',
            //                     ];
            //                 }, $withholdings),
            //                 'DatiBollo' => [
            //                     'BolloVirtuale' => $invoice->company->stampDuty->virtual_stamp ? 'SI' : '',
            //                     'ImportoBollo' => $invoice->company->stampDuty->virtual_stamp ? sprintf("%.2f", 2.00) : '',
            //                 ],
            //                 'DatiCassaPrevidenziale' => array_map(function ($fund) {
            //                     return array_filter([
            //                         'TipoCassa' => $fund['fund_code'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $fund['fund_code']) ? $fund['fund_code'] : 'TC02',
            //                         'AlCassa' => sprintf("%.2f", (float) ($fund['rate'])),
            //                         'ImportoContributoCassa' => sprintf("%.2f", (float) ($fund['amount'])),
            //                         'ImponibileCassa' => sprintf("%.2f", (float) ($fund['taxable_base'])),
            //                         'AliquotaIVA' => isset($fund['%']) && $fund['%'] !== null ? sprintf("%.2f", (float) $fund['%']) : "0.00",
            //                         'Ritenuta' => !empty($fund['withholding']) ? 'SI' : null,
            //                         'Natura' => $fund['%'] == 'N1' ? 'N1' : null,
            //                     ], fn($value) => !is_null($value) && $value !== '');
            //                 }, $funds),
            //             ],
            //             'DatiOrdineAcquisto' => $invoice->contract ? array_filter([
            //                 array_filter([
            //                     'IdDocumento' => $invoice->contract->lastDetail->number && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->contract->lastDetail->number) ? $invoice->contract->lastDetail->number : null,
            //                     'Data' => $invoice->contract->lastDetail->date ?? null,
            //                     'CodiceCUP' => $invoice->contract?->cup_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract?->cup_code) ? $invoice->contract?->cup_code : null,
            //                     'CodiceCIG' => $invoice->contract?->cig_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract?->cig_code) ? $invoice->contract?->cig_code : null,
            //                 ], fn($value) => !is_null($value) && $value !== '')
            //             ], fn($value) => !empty($value)) : [],
            //             'DatiDDT' => $invoice->delivery_note ? [
            //                 [
            //                     'NumeroDDT' => $invoice->delivery_note,
            //                     'DataDDT' => $invoice->delivery_date ?? $invoice->invoice_date->format('Y-m-d'),
            //                 ],
            //             ] : [],
            //         ],
            //         'DatiBeniServizi' => [
            //             'DettaglioLinee' => $invoice->invoiceItems->where('auto', false)->map(function ($item, $index) {
            //                 return [
            //                     'NumeroLinea' => $index + 1,
            //                     'Descrizione' => $item->description ?? 'Servizio',
            //                     'Quantita' => sprintf("%.2f", (float) ($item->quantity ?? 1.00)),
            //                     'PrezzoUnitario' => sprintf("%.2f", (float) ($item->unit_price ?? $item->amount)),
            //                     'PrezzoTotale' => sprintf("%.2f", (float) $item->amount),
            //                     'AliquotaIVA' => is_numeric($item->vat_code_type->getRate()) ? sprintf("%.2f", (float) $item->vat_code_type->getRate()) : "0.00",
            //                     'Natura' => $item->vat_code_type->getRate() == '0' ? $item->vat_code_type->getCode() : null,
            //                 ];
            //             })->toArray(),
            //             'DatiRiepilogo' => array_values(array_map(function ($vat) {
            //                 return [
            //                     'AliquotaIVA' => isset($vat['%']) && $vat['%'] !== 'N1' ? sprintf("%.2f", (float) $vat['%']) : "0.00",
            //                     'Natura' => $vat['%'] == 'N1' ? 'N1' : null,
            //                     'ImponibileImporto' => sprintf("%.2f", (float) $vat['taxable']),
            //                     'Imposta' => sprintf("%.2f", (float) $vat['vat']),
            //                     'EsigibilitaIVA' => in_array($vat['norm'][0] ?? 'I', ['D', 'I', 'S']) ? $vat['norm'][0] : 'I',
            //                     'RiferimentoNormativo' => $vat['free'] ? $vat['norm'] : null,
            //                 ];
            //             }, $invoice->updateResume($invoice->vatResume(), $invoice->getFundBreakdown()))),
            //         ],
            //         'DatiPagamento' => [
            //             [
            //                 'CondizioniPagamento' => $this->mapPaymentTypeToCondizioniPagamento($invoice->payment_type->value ?? 'TP02'),
            //                 'DettaglioPagamento' => [
            //                     [
            //                         'ModalitaPagamento' => $invoice->payment_type->getCode() ?? 'MP05',
            //                         'DataScadenzaPagamento' => $invoice->invoice_date->addDays($invoice->payment_days ?? 30)->format('Y-m-d'),
            //                         'ImportoPagamento' => sprintf("%.2f", (float) ($invoice->total ?? 0.00)),
            //                         'IBAN' => $invoice->bankAccount->iban ?? null,
            //                     ],
            //                 ],
            //             ],
            //         ],
            //     ],
            // ];

            // dd(json_encode($payload, JSON_PRETTY_PRINT));

            // Log::debug('Payload SOAP: ' . json_encode($payload, JSON_PRETTY_PRINT));

            // Esegui la chiamata SOAP per l'invio della fattura
            $response = $this->client->InviaFattura($payload);

            // dd($response);
            // Log::info('Invio-----------------------------------------------------------------------------------------');
            // Log::info('ProgressivoInvio: ' . ($response?->ProgressivoInvio ?? 'N\D'));
            // Log::info('DataOraRicezione: ' . ($response?->DataOraRicezione ?? 'N\D'));

            $input['Autenticazione'] = $this->getAutenticazione($invoice, $password);
            $input['ProgressivoInvio'] = $response->ProgressivoInvio ?? null;

            // Esegui la chiamata SOAP per il recupero dei dati di aggiornamento
            $response_s = $this->client->Stato($input);

            // dd($response_s);
            // Log::info('Stato-----------------------------------------------------------------------------------------');
            // Log::info('NomeFile : ' . ($response_s?->NomeFile ?? 'N\D'));
            // Log::info('Stato : ' . ($response_s?->Stato ?? 'N\D'));
            // Log::info('Descrizione : ' . ($response_s?->Descrizione ?? 'N\D'));
            // Log::info('Emessa : ' . ($response_s?->Emessa ?? 'N\D'));
            // Log::info('Finale : ' . ($response_s?->Finale ?? 'N\D'));
            // Log::info('DataOraCreazione : ' . ($response_s?->DataOraCreazione ?? 'N\D'));
            // Log::info('IdSdI : ' . ($response_s?->IdSdI ?? 'N\D'));
            // Log::info('DataOraRicezione : ' . ($response_s?->DataOraRicezione ?? 'N\D'));
            // Log::info('DataOraConsegna : ' . ($response_s?->DataOraConsegna ?? 'N\D'));
            // Log::info('Anomalia : ' . ($response_s?->Anomalia ?? 'N\D'));
            // Log::info('Notifica-NomeFile : ' . ($response_s?->Notifica?->NomeFile ?? 'N\D'));
            // Log::info('Notifica-DataOraRicezione : ' . ($response_s?->Notifica?->DataOraRicezione ?? 'N\D'));
            // Log::info('Notifica-Tipo : ' . ($response_s?->Notifica?->Tipo ?? 'N\D'));
            // Log::info('Notifica-ProgressivoRicezione : ' . ($response_s?->Notifica?->ProgressivoRicezione ?? 'N\D'));

            // Esegui la chiamata SOAP per il recupero dei file xml e pdf della fattura
            $responseXML = $this->client->Download($input);
            $responsePDF = $this->client->DownloadPDF($input);

            // dd($responseXML);
            // dd($responsePDF);
            // Log::info('File------------------------------------------------------------------------------------------');
            // Log::info('NomeFileXML : ' . ($responseXML?->Nome ?? 'N\D'));
            // Log::info('NomeFilePDF : ' . ($responsePDF?->Nome ?? 'N\D'));

            // dd('STOP');

            $date = explode("T", $response_s->DataOraCreazione);

            $filePathXML = $this->saveActiveXML($responseXML->Nome, $responseXML->Contenuto);               // salvo il file XML
            $filePathPDF = $this->saveActivePDF($responsePDF->Nome, $responsePDF->Contenuto);               // salvo il file PDF

            // Aggiorna stato, codici, data di invio della fattura e percorsi file XML e PDF
            $invoice->update([
                'service_code' => $response->ProgressivoInvio ?? null,
                'sdi_code' => $response_s->IdSdI ?? null,
                'sdi_status' => $this->translateStatus($response_s->Stato),
                'sdi_date' => $date[0],
                'pdf_path' => $filePathPDF,
                'xml_path' => $filePathXML
            ]);

            SdiNotification::create([
                'invoice_id' => $invoice->id,
                'code' => $response_s->IdSdI ?? null,
                'status' => $this->translateStatus($response_s->Stato),
                'date' => $date[0],
                'description' => ''
            ]);

            // Log della richiesta e risposta per debug
            // Log::debug('Richiesta SOAP: ' . $this->client->getLastRequest());
            // Log::debug('Risposta SOAP: ' . $this->client->getLastResponse());

            return $response;
        } catch (SoapFault $fault) {
            Log::error('Errore SOAP: ' . $fault->faultcode . ' - ' . $fault->faultstring);
            throw new Exception('Errore SOAP: ' . $fault->faultstring, 0, $fault);
        } catch (Exception $e) {
            Log::error('Errore generico: ' . $e->getMessage());
            throw new Exception('Errore generico: ' . $e->getMessage());
        }
    }

    private function getDate($response): string
    {
        return '1980-07-31';
    }

    public function updateStatus(Invoice $invoice, string $password)
    {
        $input['Autenticazione'] = $this->getAutenticazione($invoice, $password);
        if($invoice->service_code)
            $input['ProgressivoInvio'] = $invoice->service_code ?? null;
        else
            return null;
Log::info("Recupero stato sdi");
        $response = $this->client->Stato($input);
Log::info("Risposta"); Log::info("Risposta ", (array) $response);        // dd($response);
        // Log::info('Stato-----------------------------------------------------------------------------------------');
        // Log::info('NomeFile : ' . ($response?->NomeFile ?? 'N\D'));
        // Log::info('Stato : ' . ($response?->Stato ?? 'N\D'));
        // Log::info('Descrizione : ' . ($response?->Descrizione ?? 'N\D'));
        // Log::info('Emessa : ' . ($response?->Emessa ?? 'N\D'));
        // Log::info('Finale : ' . ($response?->Finale ?? 'N\D'));
        // Log::info('DataOraCreazione : ' . ($response?->DataOraCreazione ?? 'N\D'));
        // Log::info('IdSdI : ' . ($response?->IdSdI ?? 'N\D'));
        // Log::info('DataOraRicezione : ' . ($response?->DataOraRicezione ?? 'N\D'));
        // Log::info('DataOraConsegna : ' . ($response?->DataOraConsegna ?? 'N\D'));
        // Log::info('Anomalia : ' . ($response?->Anomalia ?? 'N\D'));
        // Log::info('Notifica-NomeFile : ' . ($response?->Notifica?->NomeFile ?? 'N\D'));
        // Log::info('Notifica-DataOraRicezione : ' . ($response?->Notifica?->DataOraRicezione ?? 'N\D'));
        // Log::info('Notifica-Tipo : ' . ($response?->Notifica?->Tipo ?? 'N\D'));
        // Log::info('Notifica-ProgressivoRicezione : ' . ($response?->Notifica?->ProgressivoRicezione ?? 'N\D'));

        $date = explode("T", $response->DataOraCreazione);                                              // la data deve essere in base allo stato?
        // $date = explode("T", $this->getDate($response));
        $newStatus = $this->translateStatus($response->Stato);
// dd($newStatus, 'STOP');
        $sdiStatus = SdiStatus::tryFrom($newStatus);
Log::info("Stato sdi: " . $newStatus);
        // $outcomes = ['rifiutata', 'accettata'];
        // if(1 == 1){
        // // if (in_array($newStatus, $outcomes)) {
        //     $responseZIP = $this->client->DownloadZip($input);

        //     if (isset($responseZIP->Contenuto)) {
        //         $fileName = $responseZIP->Nome ?? "invoice_{$invoice->id}.zip";
        //         $fileContent = base64_decode($responseZIP->Contenuto);

        //         $filePathZIP = $this->tempFromZip($responseZIP->Nome, $responseZIP->Contenuto);             // salvo temporaneamente il file ZIP

        //     }
        // }
        if($sdiStatus->sdiReceiptCode() == ''){
            $info = null;
        }
        else{
            $responseZIP = $this->client->DownloadZip($input);
            $info = $this->processResponse($sdiStatus, $responseZIP);
        }
// dd('STOP');
        if($invoice->sdi_status->value != $newStatus && $invoice->sdi_status->updateStatus()){              // modifico se è diverso da quello esistente
                                                                                                            // e questo non è RIFIUTO_EMESSO, RIFIUTO_ARCHIVIATO, SCARTO_VALIDATO,
                                                                                                            // MANCATA_CONSEGNA_VALIDATA, AUTO_INVIATA, APERTA
        // Aggiorno stato e data modifica stato della fattura
Log::info("Aggiornamento stato sdi");
            $invoice->update([
                'sdi_code' => $response?->IdSdI,
                'sdi_status' => $newStatus,
                'sdi_info' => $info,
                'sdi_date' => $date[0]
            ]);
Log::info("Creazione notitifca sdi");
            SdiNotification::create([
                    'invoice_id' => $invoice->id,
                    'code' => $invoice->sdi_code ?? null,
                    'status' => $newStatus,
                    'date' => $date[0],
                    'description' => $info
                ]);
        }

        return $response;
    }

    private function processResponse($sdiStatus, $responseZIP): ?string
    {
        $zipContent = $responseZIP->Contenuto;
        $zipName = $responseZIP->Nome;
        $receiptCode = $sdiStatus->sdiReceiptCode();
// dd($sdiStatus, 'STOP');
        try{
            // Salvo lo ZIP
            $livewireDisk = config('livewire.temporary_file_upload.disk', 'local');
            Storage::put('temp/' . $zipName, $zipContent);

            // Estraggo i file dello ZIP
            $zip = new ZipArchive;
            $zipPath = Storage::path('temp/' . $zipName);

            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException("Impossibile aprire il file ZIP: {$zipName}");
            }


            $extractPath = Storage::path('extracted/' . pathinfo($zipName, PATHINFO_FILENAME));
            $zip->extractTo($extractPath);
            $zip->close();

            // Leggo i file estratti
            $files = Storage::files('extracted/' . pathinfo($zipName, PATHINFO_FILENAME));

            // Trovo il file con il codice ricevuta
            $file = collect($files)->first(fn($file) => str_contains($file, $receiptCode));

            if (!$file) {
                \Log::warning("File con codice {$receiptCode} non trovato nello ZIP", [
                    'zip' => $zipName,
                    'files' => $files
                ]);
                return null;
            }

            $content = Storage::get($file);
            $xmlArray = $this->xmlToArray($content);
// dd($xmlArray, 'STOP');
            $output = $this->parseReceiptData($receiptCode, $xmlArray);
// dd($output, 'STOP');
            $info = $this->createInfo($receiptCode, $output);
// dd($info, 'STOP');
            // Cleanup
            Storage::delete($files);
            Storage::delete('temp/' . $zipName);

            return $info;
        } catch (Exception $e) {
            \Log::error("Errore nel processare la risposta SDI", [
                'zip' => $zipName,
                'receiptCode' => $receiptCode,
                'error' => $e->getMessage()
            ]);
            return null;
        } finally {
            // Cleanup sempre eseguito
            $this->cleanupFiles($zipName);
        }
    }

    private function parseReceiptData(string $receiptCode, array $xmlArray): ?array
    {
        $output = [];

        switch ($receiptCode) {
            case '_NS_':                                                                                        // notifica di scarto
                $output[$receiptCode]['ListaErrori'] = $xmlArray['ListaErrori'] ?? '';                          // array di elementi composti da ['Codice'] e ['Descrizione']
                break;
            case '_RC_':                                                                                        // ricevuta di consegna
                $output[$receiptCode]['DataOraConsegna'] = $xmlArray['DataOraConsegna'] ?? '';                  // stringa in formato ISO 8601:2004 (2026-01-01T12:00:00)
                break;
            case '_MC_':                                                                                        // mancato recapito
                $output[$receiptCode]['Descrizione'] = $xmlArray['Descrizione'] ?? '';                          // stringa
                $output[$receiptCode]['Note'] = $xmlArray['Note'] ?? '';                                        // stringa
                break;
            case '_NE_':                                                                                        // notifica di esito
                $output[$receiptCode]['Esito'] = $xmlArray['EsitoCommittente']['Esito'] ?? '';                  // EC01/EC02
                $output[$receiptCode]['Descrizione'] = $xmlArray['EsitoCommittente']['Descrizione'] ?? '';      // stringa
                break;
            case '_DT_':                                                                                        // decorsi i termini
                $output[$receiptCode]['Descrizione'] = $xmlArray['Descrizione'] ?? '';                          // stringa
                $output[$receiptCode]['Note'] = $xmlArray['Note'] ?? '';                                        // stringa
                break;
            case '_AT_':                                                                                        // impossibilità di recapito
                $output[$receiptCode]['Note'] = $xmlArray['Note'] ?? '';                                        // stringa
                break;
            default:
                \Log::warning("Codice ricevuta SDI sconosciuto: {$receiptCode}");
                return null;
        }

        return $output;
    }

    private function createInfo(string $receiptCode, array $output): string                                     // creazione stringa informativa della notifica SDI
    {
        $info = '';

        switch ($receiptCode) {
            case '_NS_':                                                                                        // notifica di scarto
                foreach($output[$receiptCode]['ListaErrori'] as $errore){
                    $info .= "Codice errore: {$errore['Codice']} - Descrizione: {$errore['Descrizione']}\n";
                }
                break;
            case '_RC_':                                                                                        // ricevuta di consegna
                $dt = new DateTime($output[$receiptCode]['DataOraConsegna']);
                $date = $dt->format('d/m/Y');
                $time = $dt->format('H:i');
                $info .= "Consegnato il {$date} alle {$time}";
                break;
            case '_MC_':                                                                                        // mancato recapito
                $info .= "{$output[$receiptCode]['Descrizione']}\n{$output[$receiptCode]['Note']}";
                break;
            case '_NE_':                                                                                        // notifica di esito
                $info .= "{$output[$receiptCode]['Esito']} - {$output[$receiptCode]['Descrizione']}";
                break;
            case '_DT_':                                                                                        // decorsi i termini
                $info .= "{$output[$receiptCode]['Descrizione']}\n{$output[$receiptCode]['Note']}";
                break;
            case '_AT_':                                                                                        // impossibilità di recapito
                $info .= "{$output[$receiptCode]['Note']}";
                break;
            default:
                \Log::warning("Codice ricevuta SDI sconosciuto: {$receiptCode}");
        }

        return $info;
    }

    private function cleanupFiles(string $zipName): void                                                        // pulizia cartelle temporanee
    {
        try {
            $extractFolder = 'extracted/' . pathinfo($zipName, PATHINFO_FILENAME);

            // Elimina i file estratti
            if (Storage::exists($extractFolder)) {
                Storage::deleteDirectory($extractFolder);
            }

            // Elimina lo ZIP temporaneo
            if (Storage::exists('temp/' . $zipName)) {
                Storage::delete('temp/' . $zipName);
            }
        } catch (Exception $e) {
            \Log::error("Errore durante il cleanup dei file SDI", [
                'zip' => $zipName,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateStatusList($list, string $password)
    {
Log::info('Inizio ciclo ' . '====================================================================');
        foreach($list as $el){
Log::info('Update fattura ' . $el->getNewInvoiceNumber() . '-------------------------------------');
            $response = $this->updateStatus($el, $password);
        }

        SdiRequest::create([
            'company_id' => Filament::getTenant()->id,
            'request_date' => today()->format('Y-m-d'),
            'sdi_request_type' => 'mass',
            'invoice_id' => null
        ]);
    }

    private function xmlToArray($xmlString) {
        try {
            // Carico la stringa XML in SimpleXMLElement, gestisco namespaces and CDATA
            $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                throw new Exception('Lettura XML fallita');
            }

            // Recupero i namespaces
            $namespaces = $xml->getNamespaces(true);
            $ns = isset($namespaces['ns3']) ? 'ns3' : (isset($namespaces['']) ? '' : key($namespaces));

            // Debug: Log namespaces
            // error_log('Namespaces: ' . print_r($namespaces, true));

            // Converto ricorsivamente XML in array
            $result = $this->xmlElementToArray($xml, $ns);

            // Debug: Log raw result
            // error_log('Parsed Result: ' . print_r($result, true));

            return $result;
        } catch (Exception $e) {
            return ['error' => 'Errore lettura XML: ' . $e->getMessage()];
        }
    }

    private function xmlElementToArray($element, $ns = '') {
        $result = [];

        // Gestisco gli attributi
        foreach ($element->attributes() as $attrName => $attrValue) {
            $result['@attributes'][$attrName] = (string)$attrValue;
        }

        // Gestisco i figli con namespace
        $children = $ns ? $element->children($ns, true) : $element->children();
        if ($children->count() == 0 && $ns) {
            // Se non ci sono figli provo senza namespace
            $children = $element->children();
        }

        foreach ($children as $childName => $child) {
            // Debug: Log child name
            // error_log('Processing child: ' . $childName);

            // Se il figlio ha figli processo ricorsivamente
            if ($child->count() > 0 || $child->attributes()->count() > 0) {
                $childArray = $this->xmlElementToArray($child, $ns);
            } else {
                $childArray = trim((string)$child);
            }

            // Gestisco elementi ripetuti
            if (isset($result[$childName])) {
                if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                    $result[$childName] = [$result[$childName]];
                }
                $result[$childName][] = $childArray;
            } else {
                $result[$childName] = $childArray;
            }
        }

        // Se non ci sono figli o attributi ritorno il contenuto
        if (empty($result) && trim((string)$element) !== '') {
            return trim((string)$element);
        }

        return $result;
    }

    // private function tempFromZip(string $filename, string $content): array
    // {
    //     $disk = config('filesystems.default');
    //     $extractedPaths = [];

    //     // 1. Creiamo un file temporaneo fisico per poterlo leggere con ZipArchive
    //     $tempZipPath = tempnam(sys_get_temp_dir(), 'sdi_');
    //     file_put_contents($tempZipPath, base64_decode($content));

    //     $zip = new ZipArchive;

    //     if ($zip->open($tempZipPath) === TRUE) {
    //         // 2. Iteriamo su tutti i file presenti nello ZIP
    //         for ($i = 0; $i < $zip->numFiles; $i++) {
    //             $innerFilename = $zip->getNameIndex($i);
    //             $innerContent = $zip->getFromIndex($i);

    //             // Definiamo dove salvare il file estratto (es. invoices/extracted/nomefile.xml)
    //             $relativePath = 'invoices/extracted/' . $innerFilename;

    //             if (Storage::disk($disk)->put($relativePath, $innerContent)) {
    //                 $extractedPaths[] = $relativePath;
    //             } else {
    //                 throw new \Exception("Errore durante il salvataggio del file estratto: $innerFilename");
    //             }
    //         }
    //         $zip->close();
    //     } else {
    //         throw new \Exception("Impossibile aprire il pacchetto ZIP SDI: $filename");
    //     }

    //     // 3. Pulizia: eliminiamo lo ZIP temporaneo
    //     if (file_exists($tempZipPath)) {
    //         unlink($tempZipPath);
    //     }

    //     // Ritorna l'elenco dei percorsi dei file salvati
    //     return $extractedPaths;
    // }

    private function saveActiveXML(string $filename, string $content): string
    {
        // Definisco il percorso relativo per il file XML
        $relativePath = 'invoices/xml_files/' . $filename;

        $disk = config('filesystems.default');

        // Salvo il file usando il disco 'public'
        if (Storage::disk($disk)->put($relativePath, $content)) {
        // if (Storage::disk('public')->put($relativePath, $content)) {
            return $relativePath; // Restituisco il percorso relativo
        } else {
            throw new Exception("Errore durante il salvataggio del file XML: $filename");
        }
    }

    private function saveActivePDF(string $filename, string $content): string
    {
        // Definisco il percorso relativo per il file PDF
        $relativePath = 'invoices/pdf_files/' . $filename;

        $disk = config('filesystems.default');

        // Salvo il file usando il disco 'public'
        if (Storage::disk($disk)->put($relativePath, $content)) {
        // if (Storage::disk('public')->put($relativePath, $content)) {
            return $relativePath; // Restituisco il percorso relativo
        } else {
            throw new Exception("Errore durante il salvataggio del file PDF: $filename");
        }
    }

    private function savePassiveXML(string $filename, string $content): string
    {
        // Definisco il percorso relativo per il file XML
        $relativePath = 'passive_invoices/xml_files/' . $filename;

        $disk = config('filesystems.default');

        // Salvo il file usando il disco 'public'
        if (Storage::disk($disk)->put($relativePath, $content)) {
        // if (Storage::disk('public')->put($relativePath, $content)) {
            return $relativePath; // Restituisco il percorso relativo
        } else {
            throw new Exception("Errore durante il salvataggio del file XML: $filename");
        }
    }

    private function savePassivePDF(string $filename, string $content): string
    {
        // Definisco il percorso relativo per il file PDF
        $relativePath = 'passive_invoices/pdf_files/' . $filename;

        $disk = config('filesystems.default');

        // Salvo il file usando il disco 'public'
        if (Storage::disk($disk)->put($relativePath, $content)) {
        // if (Storage::disk('public')->put($relativePath, $content)) {
            return $relativePath; // Restituisco il percorso relativo
        } else {
            throw new Exception("Errore durante il salvataggio del file PDF: $filename");
        }
    }

    private function savePassiveAttachment(PassiveInvoice $passiveInvoice, string $filename, string $content): string
    {
        // Definisco il percorso relativo per il file allegato
        $relativePath = 'passive_invoices/attachments/' . $passiveInvoice->id . '/' . $filename;

        $disk = config('filesystems.default');

        // Decodifico il Base64 e salvo il file
        $decodedContent = base64_decode($content);

        if (Storage::disk($disk)->put($relativePath, $decodedContent)) {
            return 'passive_invoices/attachments/' . $passiveInvoice->id; // Restituisco il percorso relativo
        } else {
            throw new Exception("Errore durante il salvataggio dell'allegato: $filename");
        }
    }

    private function checkSupplier(array $param): array
    {
        $output['new'] = false;
        // $supplier = Supplier::where('vat_code', $param['CedentePrestatore']['DatiAnagrafici']['IdFiscaleIVA']['IdCodice'])
        //                     ->orWhere('tax_code', $param['CedentePrestatore']['DatiAnagrafici']['IdFiscaleIVA']['IdCodice'])
        //                     ->orWhere('tax_code', $param['CedentePrestatore']['DatiAnagrafici']['CodiceFiscale'])
        //                     ->first();

        $query = Supplier::query();

        // $vatCode = data_get($param, 'CedentePrestatore.DatiAnagrafici.IdFiscaleIVA.IdCodice');
        // $taxCode1 = data_get($param, 'CedentePrestatore.DatiAnagrafici.IdFiscaleIVA.IdCodice'); // può essere lo stesso del precedente
        // $taxCode2 = data_get($param, 'CedentePrestatore.DatiAnagrafici.CodiceFiscale');

        $idCountry = data_get($param, 'CedentePrestatore.DatiAnagrafici.IdFiscaleIVA.IdPaese');
        $denomination = data_get($param, 'CedentePrestatore.DatiAnagrafici.Anagrafica.Denominazione');
        $temp = data_get($param, 'CedentePrestatore.DatiAnagrafici.IdFiscaleIVA.IdCodice');
        $taxCode2 = data_get($param, 'CedentePrestatore.DatiAnagrafici.CodiceFiscale');
        $vatCode = '';
        $taxCode1 = '';

        if ($idCountry =='IT'){                                                                                 // il fornitore è italiano
            if ($temp) {
                $lunghezza = strlen($temp);

                if ($lunghezza === 11) {
                    // Formato italiano standard (11 caratteri)
                    $vatCode = $temp;
                } elseif ($lunghezza === 13) {
                    // Formato estero con prefisso (es: IT12345678901)
                    // Rimuove i primi due caratteri (il codice paese) e assegna il risultato.
                    $vatCode = substr($temp, 2);
                } else {
                    // Gestisci eventuali lunghezze anomale o lascia stringa vuota
                    $vatCode = '';
                }

                // Assegna la Partita IVA pulita anche a $taxCode1 (come richiesto nella logica precedente)
                // Se non vuoi che $taxCode1 sia uguale a $vatCode, puoi rimuovere la riga successiva.
                $taxCode1 = $vatCode;
            }

            // if ($vatCode) {
            //     $query->orWhere('vat_code', $vatCode);
            // }
            // if ($taxCode1) {
            //     $query->orWhere('tax_code', $taxCode1);
            // }
            // if ($taxCode2) {
            //     $query->orWhere('tax_code', $taxCode2);
            // }

            $query->where(function ($q) use ($vatCode, $taxCode1, $taxCode2) {
                if ($vatCode)  $q->orWhere('vat_code', $vatCode);
                if ($taxCode1) $q->orWhere('tax_code', $taxCode1);
                if ($taxCode2) $q->orWhere('tax_code', $taxCode2);
            });
        }
        else {                                                                                                  // il fornitore è estero
            $query->where('country', $idCountry)
                    ->where('denomination', $denomination);
        }

        $supplier = $query->first();

        // dd($supplier);

        if(!$supplier){ $supplier = $this->createNewSupplier($output, $param['CedentePrestatore']); }

        if (!$supplier) {
            $supplier = Supplier::where('denomination', 'LIKE', '%Estero%')
                ->orderBy('id', 'asc')
                ->first();
        }

        // dd($supplier);

        $output['supplier'] = $supplier;

        // dd($output);

        return $output;
    }

    private function createNewSupplier(array &$output, array $cedente): ?Supplier
    {
        try {
            $data = [
                'company_id'            => Filament::getTenant()->id,

                'denomination'          => Str::limit($cedente['DatiAnagrafici']['Anagrafica']['Denominazione'] ??
                                            trim(($cedente['DatiAnagrafici']['Anagrafica']['Cognome'] ?? '') . ' ' . ($cedente['DatiAnagrafici']['Anagrafica']['Nome'] ?? '')), 255),
                'tax_code'              => Str::limit($cedente['DatiAnagrafici']['CodiceFiscale'] ?? null, 255),
                'vat_code'              => Str::limit($cedente['DatiAnagrafici']['IdFiscaleIVA']['IdCodice'] ?? null, 255),

                'address'               => Str::limit($cedente['Sede']['Indirizzo'] ?? null, 255),
                'civic_number'          => Str::limit($cedente['Sede']['NumeroCivico'] ?? null, 255),
                'zip_code'              => Str::limit($cedente['Sede']['CAP'] ?? null, 255),
                'city'                  => Str::limit($cedente['Sede']['Comune'] ?? null, 255),
                'province'              => Str::limit($cedente['Sede']['Provincia'] ?? null, 255),
                'country'               => Str::limit($cedente['Sede']['Nazione'] ?? null, 255),

                'rea_office'            => Str::limit($cedente['IscrizioneREA']['Ufficio'] ?? null, 255),
                'rea_number'            => Str::limit($cedente['IscrizioneREA']['NumeroREA'] ?? null, 255),
                'capital'               => Str::limit($cedente['IscrizioneREA']['CapitaleSociale'] ?? null, 255),
                'sole_share'            => Str::limit($cedente['IscrizioneREA']['SocioUnico'] ?? null, 255),
                'liquidation_status'    => $cedente['IscrizioneREA']['StatoLiquidazione'] ?? null,

                'phone'                 => Str::limit($cedente['Contatti']['Telefono'] ?? null, 255),
                'fax'                   => Str::limit($cedente['Contatti']['Fax'] ?? null, 255),
                'email'                 => Str::limit($cedente['Contatti']['Email'] ?? null, 255),
                'pec'                   => null
            ];

            $newSupplier = Supplier::create($data);

            if ($newSupplier) {
                $output['new'] = true;
                return $newSupplier;
            }

            return null;
        } catch (Exception $e) {
            // Se c'è un errore (es. database) restituiamo null per attivare il fallback Estero
            return null;
        }
    }

    private function createPassiveInvoice(array $param): PassiveInvoice
    {
        $supplier = $param['supplier'];                                                                                 // fornitore
        $xml = $param['content'];                                                                                       // array xml
        $item = $param['item'];
        $parentPassiveInvoice = null;

        // dd($xml);

        $td = $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['TipoDocumento'] ?? null;
        $rawCausale = $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['Causale'] ?? null;
        if (isset($xml['FatturaElettronicaBody']['DatiGenerali']['DatiFattureCollegate'])) {
            $collegate = $xml['FatturaElettronicaBody']['DatiGenerali']['DatiFattureCollegate'];
            $fattColl = $collegate['IdDocumento'] ?? null;
            $dataFattColl = null;
            if (!empty($collegate['Data'])) {
                $dataTemp = explode('-', $collegate['Data']);

                // Controlliamo che l'explode abbia prodotto effettivamente 3 parti (Y-m-d)
                if (count($dataTemp) === 3) {
                    $dataFattColl = $dataTemp[2] . '-' . $dataTemp[1] . '-' . $dataTemp[0];
                }
            }
            if(!$rawCausale && ($fattColl && $dataFattColl)){
                $rawCausale = 'Fatt.Coll. ' . $fattColl .' del ' . $dataFattColl;
            }
            $parentPassiveInvoice = $this->getParentPassiveInvoice($collegate, $supplier->id);
        }

        $data = [
                'company_id' => Filament::getTenant()->id,
                'supplier_id' => $supplier->id,
                'parent_id' => $parentPassiveInvoice?->id,
                'doc_type' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['TipoDocumento'] ?? null,
                'invoice_date' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['Data'] ?? null,
                'number' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['Numero'] ?? null,
                'description' => is_array($rawCausale) ? implode('; ', $rawCausale) : $rawCausale,
                'total_doc' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['ImportoTotaleDocumento'] ?? null,
                'total' => null,
                'sdi_code' => $item->IdentificativoSdI,
                'sdi_status' => $item->Stato,
                'payment_mode' => $xml['FatturaElettronicaBody']['DatiPagamento']['CondizioniPagamento'] ?? null,
                'payment_type' => $xml['FatturaElettronicaBody']['DatiPagamento']['DettaglioPagamento']['ModalitaPagamento'] ?? null,
                'payment_deadline' => $xml['FatturaElettronicaBody']['DatiPagamento']['DettaglioPagamento']['DataScadenzaPagamento'] ?? null,
                'bank' => $xml['FatturaElettronicaBody']['DatiPagamento']['DettaglioPagamento']['IstitutoFinanziario'] ?? null,
                'iban' => $xml['FatturaElettronicaBody']['DatiPagamento']['DettaglioPagamento']['IBAN'] ?? null,
                'filename' => explode('.', $item->NomeFile)[0] ?? null,
                'xml_path' => $param['filePath_xml'] ?? null,
                'pdf_path' => $param['filePath_pdf'] ?? null
            ];

        // dd(var_dump($data));

        $passiveInvoice = PassiveInvoice::create($data);

        // dd($passiveInvoice);

        return $passiveInvoice;
    }

    private function getParentPassiveInvoice(array $collegate, $supplierId): PassiveInvoice | null
    {
        return PassiveInvoice::where('supplier_id', $supplierId)->where('number', $collegate['IdDocumento'])->where('invoice_date', $collegate['Data'])->first();
    }

    private function createDetailItems(array $param): int
    {
        $items = 0;
        $details = $param['content']['FatturaElettronicaBody']['DatiBeniServizi']['DettaglioLinee'] ?? null;

        if (!empty($details)) {

            $array = !empty($details) && array_reduce($details, function ($carry, $item) {
                        return $carry && is_array($item);
                    }, true);

            if($array){
                foreach ($details as $detail) {
                    $data = [
                        'company_id' => Filament::getTenant()->id,
                        'passive_invoice_id' => $param['passive_invoice']->id,
                        'description' => $detail['Descrizione'] ?? null,
                        'quantity' => $detail['Quantita'] ?? null,
                        'unit_price' => $detail['PrezzoUnitario'] ?? null,
                        'total_price' => $detail['PrezzoTotale'] ?? null,
                        'vat_rate' => $detail['AliquotaIVA'] ?? null
                    ];

                    $nvoiceDetail = PassiveItem::create($data);
                    $items++;
                }
            }
            else{
                $data = [
                    'company_id' => Filament::getTenant()->id,
                    'passive_invoice_id' => $param['passive_invoice']->id,
                    'description' => $details['Descrizione'] ?? null,
                    'quantity' => $details['Quantita'] ?? null,
                    'unit_price' => $details['PrezzoUnitario'] ?? null,
                    'total_price' => $details['PrezzoTotale'] ?? null,
                    'vat_rate' => $details['AliquotaIVA'] ?? null
                ];

                $invoiceDetail = PassiveItem::create($data);
                $items++;
            }
        }

        return $items;
    }

    private function createResumeItems(array $param): int
    {
        $items = 0;

        $resumes = $param['content']['FatturaElettronicaBody']['DatiBeniServizi']['DatiRiepilogo'] ?? null;

        // dd($param);

        if (!empty($resumes)) {

            Log::info('Resume items processing', [
                'IdentificativoSdI' => $param['item']->IdentificativoSdI,
            ]);


            $array = !empty($resumes) && array_reduce($resumes, function ($carry, $item) {
                        return $carry && is_array($item);
                    }, true);

            if ($array) {
                // dd('ARRAY');
                foreach ($resumes as $resume) {
                    $collectability = '*';
                    if (isset($resume['EsigibilitaIVA'])) {
                        switch ($resume['EsigibilitaIVA']) {
                            case 'I':
                                $collectability = 'Immediata';
                                break;
                            case 'D':
                                $collectability = 'Differita';
                                break;
                            case 'S':
                                $collectability = 'Scissione';
                                break;
                        }
                    }
                    $data = [
                        'company_id' => Filament::getTenant()->id,
                        'passive_invoice_id' => $param['passive_invoice']->id,
                        'description' => 'Riepilogo - ' . ($resume['Natura'] ?? '*') . ' - ' . $collectability . ' - ' . ($resume['RiferimentoNormativo'] ?? '*'),
                        'quantity' => null,
                        'unit_price' => null,
                        'total_price' => $resume['Imposta'] ?? null,
                        'vat_rate' => $resume['AliquotaIVA'] ?? null
                    ];

                    $invoiceDetail = PassiveItem::create($data);
                    $items++;
                }
            }
            else {
                // dd('SINGOLO');
                $collectability = '*';
                if (isset($resumes['EsigibilitaIVA'])) {
                    switch ($resumes['EsigibilitaIVA']) {
                        case 'I':
                            $collectability = 'Immediata';
                            break;
                        case 'D':
                            $collectability = 'Differita';
                            break;
                        case 'S':
                            $collectability = 'Scissione';
                            break;
                    }
                }
                $data = [
                    'company_id' => Filament::getTenant()->id,
                    'passive_invoice_id' => $param['passive_invoice']->id,
                    'description' => 'Riepilogo - ' . ($resumes['Natura'] ?? '*') . ' - ' . $collectability . ' - ' . ($resumes['RiferimentoNormativo'] ?? '*'),
                    'quantity' => null,
                    'unit_price' => null,
                    'total_price' => $resumes['Imposta'] ?? null,
                    'vat_rate' => $resumes['AliquotaIVA'] ?? null
                ];

                $invoiceDetail = PassiveItem::create($data);
                $items++;
            }
        }

        return $items;
    }

    private function createFundItems(array $param): int
    {
        $items = 0;

        $funds = $param['content']['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['DatiCassaPrevidenziale'] ?? null;

        // dd($funds);

        if (!empty($funds)) {

            // dd($funds);

            $array = !empty($funds) && array_reduce($funds, function ($carry, $item) {
                        return $carry && is_array($item);
                    }, true);

            if ($array) {

                // dd('ARRAY');

                foreach ($funds as $fund) {
                    // Ottieni l'istanza dell'enum FundType basata su TipoCassa
                    $description = isset($fund['TipoCassa']) && is_string($fund['TipoCassa'])
                        ? collect(FundType::cases())
                            ->first(fn($case) => $case->getCode() === $fund['TipoCassa'])
                            ?->getDescription() ?? null
                        : null;
                    // Usa getDescription() se fundType esiste, altrimenti null
                    // $description = $fundType?->getDescription() ?? null;

                    $data = [
                        'company_id' => Filament::getTenant()->id,
                        'passive_invoice_id' => $param['passive_invoice']->id,
                        'description' => 'Cassa prev. - ' . $description,
                        'quantity' => null,
                        'unit_price' => null,
                        'total_price' => $fund['ImportoContributoCassa'] ?? null, // Corretto il typo
                        'vat_rate' => $fund['AliquotaIVA'] ?? null
                    ];

                    $invoiceDetail = PassiveItem::create($data);
                    $items++;
                }
            }
            else {

                // dd('SINGOLO');

                $description = isset($funds['TipoCassa']) && is_string($funds['TipoCassa'])
                    ? collect(FundType::cases())
                        ->first(fn($case) => $case->getCode() === $funds['TipoCassa'])
                        ?->getDescription() ?? null
                    : null;

                // dd($param['passive_invoice']->id  . ": " . $fundType);
                // dd('STOP1');

                // Usa getDescription() se fundType esiste, altrimenti null
                // $description = $fundType?->getDescription() ?? null;

                $data = [
                    'company_id' => Filament::getTenant()->id,
                    'passive_invoice_id' => $param['passive_invoice']->id,
                    'description' => 'Cassa prev. - ' . $description,
                    'quantity' => null,
                    'unit_price' => null,
                    'total_price' => $funds['ImportoContributoCassa'] ?? null, // Corretto il typo
                    'vat_rate' => $funds['AliquotaIVA'] ?? null
                ];

                // dd('STOP2');

                $invoiceDetail = PassiveItem::create($data);
                $items++;
            }
        }

        return $items;
    }

    private function createWithholdingItems(array $param): int
    {
        $items = 0;

        $withholdings = $param['content']['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['DatiRitenuta'] ?? null;

        // dd($funds);

        if (!empty($withholdings)) {

            // dd($withholdings);

            $array = !empty($withholdings) && array_reduce($withholdings, function ($carry, $item) {
                        return $carry && is_array($item);
                    }, true);

            if ($array) {

                // dd('ARRAY');

                foreach ($withholdings as $withholding) {
                    // Ottieni l'istanza dell'enum FundType basata su TipoCassa
                    $description = isset($withholding['TipoRitenuta']) && is_string($withholding['TipoRitenuta'])
                        ? collect(WithholdingType::cases())
                            ->first(fn($case) => $case->getCode() === $withholding['TipoRitenuta'])
                            ?->getDescription() ?? null
                        : null;

                    $reason = isset($withholding['CausalePagamento']) && is_string($withholding['CausalePagamento'])
                        ? collect(WithholdingType::cases())
                            ->first(fn($case) => $case->getCode() === $withholding['CausalePagamento'])
                            ?->getDescription() ?? null
                        : null;

                    $data = [
                        'company_id' => Filament::getTenant()->id,
                        'passive_invoice_id' => $param['passive_invoice']->id,
                        'description' => $description . ' - ' . $reason,
                        'quantity' => null,
                        'unit_price' => null,
                        'total_price' => isset($withholding['ImportoRitenuta']) ? $withholding['ImportoRitenuta'] * -1 : null,
                        'vat_rate' => $withholding['AliquotaRitenuta'] ?? null
                    ];

                    $invoiceDetail = PassiveItem::create($data);
                    $items++;
                }
            }
            else {

                // dd('SINGOLO');

                $description = isset($withholdings['TipoRitenuta']) && is_string($withholdings['TipoRitenuta'])
                    ? collect(WithholdingType::cases())
                        ->first(fn($case) => $case->getCode() === $withholdings['TipoRitenuta'])
                        ?->getDescription() ?? null
                    : null;

                $reason = isset($withholdings['CausalePagamento']) && is_string($withholdings['CausalePagamento'])
                    ? collect(WithholdingType::cases())
                        ->first(fn($case) => $case->getCode() === $withholdings['CausalePagamento'])
                        ?->getDescription() ?? null
                    : null;

                $data = [
                    'company_id' => Filament::getTenant()->id,
                    'passive_invoice_id' => $param['passive_invoice']->id,
                    'description' => $description . ' - ' . $reason,
                    'quantity' => null,
                    'unit_price' => null,
                    'total_price' => $withholdings['ImportoRitenuta'] ?? null,
                    'vat_rate' => $withholdings['AliquotaRitenuta'] ?? null
                ];

                $invoiceDetail = PassiveItem::create($data);
                $items++;
            }
        }

        return $items;
    }

    private function createPassiveItems(array $param): int
    {
        $detailsNumber = 0;
        $resumesNumber = 0;
        $fundsNumber = 0;

        // // Normalizza DettaglioLinee in un array
        // $details = $param['content']['FatturaElettronicaBody']['DatiBeniServizi']['DettaglioLinee'] ?? [];
        // $details = is_array($details) ? $details : [$details]; // Converti in array se è un elemento singolo

        // if (!empty($details)) {
        //     foreach ($details as $detail) {
        //         $data = [
        //             'company_id' => Filament::getTenant()->id,
        //             'passive_invoice_id' => $param['passive_invoice']->id,
        //             'description' => $detail['Descrizione'] ?? null,
        //             'quantity' => $detail['Quantita'] ?? null,
        //             'unit_price' => $detail['PrezzoUnitario'] ?? null,
        //             'total_price' => $detail['PrezzoTotale'] ?? null,
        //             'vat_rate' => $detail['AliquotaIVA'] ?? null
        //         ];

        //         $nvoiceDetail = PassiveItem::create($data);
        //         $detailsNumber++;
        //     }
        // }

        $detailsNumber = $this->createDetailItems($param);                                                      // creo voci fattura da DettaglioLinee

        // Normalizza DatiRiepilogo in un array
        // $resumes = $param['content']['FatturaElettronicaBody']['DatiBeniServizi']['DatiRiepilogo'] ?? [];
        // $resumes = is_array($resumes) ? $resumes : [$resumes]; // Converti in array se è un elemento singolo

        // if (!empty($resumes)) {
        //     foreach ($resumes as $resume) {
        //         $data = [
        //             'company_id' => Filament::getTenant()->id,
        //             'passive_invoice_id' => $param['passive_invoice']->id,
        //             'description' => $resume['RiferimentoNormativo'] ?? null,
        //             'quantity' => null,
        //             'unit_price' => null,
        //             'total_price' => $resume['Imposta'] ?? null,
        //             'vat_rate' => $resume['AliquotaIVA'] ?? null
        //         ];

        //         $nvoiceDetail = PassiveItem::create($data);
        //         $detailsNumber++;
        //     }
        // }

        $withholdingsNumber = $this->createWithholdingItems($param);                                            // creo voci fattura da DatiRitenuta

        $resumesNumber = $this->createResumeItems($param);                                                      // creo voci fattura da DatiRiepilogo

        // Normalizza DatiRiepilogo in un array
        // $funds = $param['content']['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['DatiCassaPrevidenziale'] ?? [];

        // $funds = is_array($funds) ? $funds : [$funds]; // Converti in array se è un elemento singolo

        // if (!empty($funds)) {
        //     foreach ($funds as $fund) {
        //         dd($fund);
        //         // Ottieni l'istanza dell'enum FundType basata su TipoCassa
        //         $fundType = isset($fund['TipoCassa']) && is_string($fund['TipoCassa'])
        //             ? FundType::tryFrom($fund['TipoCassa'])
        //             : null;
        //         // Usa getDescription() se fundType esiste, altrimenti null
        //         $description = $fundType?->getDescription() ?? null;

        //         $data = [
        //             'company_id' => Filament::getTenant()->id,
        //             'passive_invoice_id' => $param['passive_invoice']->id,
        //             'description' => $description,
        //             'quantity' => null,
        //             'unit_price' => null,
        //             'total_price' => $fund['ImportoContributoCassa'] ?? null, // Corretto il typo
        //             'vat_rate' => $fund['AliquotaIVA'] ?? null
        //         ];

        //         $nvoiceDetail = PassiveItem::create($data);
        //         $detailsNumber++;
        //     }
        // }

        $fundsNumber = $this->createFundItems($param);                                                      // creo voci fattura da DatiCassaPrevidenziale

        return $withholdingsNumber + $detailsNumber + $resumesNumber + $fundsNumber;
    }

    public function downloadPassive(array $data)
    {
        try {

            DB::beginTransaction();

            $latest = DB::table('passive_downloads')->orderBy('date', 'desc')->first();
            $dataInizio = $latest ? Carbon::parse($latest->date)->addDay()->toDateString() : '1970-01-01';
            $dataFine = Carbon::yesterday()->toDateString();

            $input = [
                'Autenticazione' => $this->getAutenticazione(null, $data['password']),
                'IncludiArchiviate' => false,

                // filtro ricerca fatture passive con data ultimo scarico
                // 'DataInizio' => $dataInizio,                                                                    // Formato: YYYY-MM-DD
                // 'DataOraInizio' => $dataInizio . 'T00:00:00',                                                   // Formato: YYYY-MM-DDThh:mm:ss
                // 'DataFine' => $dataFine,                                                                        // Formato: YYYY-MM-DD
                // 'DataOraFine' => $dataFine . 'T23:59:59',                                                       // Formato: YYYY-MM-DDThh:mm:ss

                // 'Limite' => 1, // Opzionale, se vuoi limitare il numero di fatture
                // 'DataParam' => 'data_fattura', // Opzionale, se vuoi specificare il tipo di data
            ];
            // if($data['limit'])
            //         $input['Limite'] = $data['limit'];
            // $input['Tags'] = [
            //     ['contabilizzata'] => true,
            //     ['corretta'] => true,
            //     ['da_verificare'] => true,
            //     ['inviata'] => true,
            //     ['letta'] => true,
            //     ['pagata'] => true,
            //     ['pagata_parziale'] => true,
            //     ['scaricata'] => true,
            //     ['stampata'] => true
            // ];
            // $input['DataParam'] = 'data_fattura';                                                           // 'data_sistema', 'data_fattura', 'data_corrispettivo'
            // $input['useTags'] = false;
            // $input['tContabilizzata'] = false;
            // $input['tCorretta'] = false;
            // $input['tDaVerificare'] = false;
            // $input['tInviata'] = false;
            // $input['tLetta'] = false;
            // $input['tPagata'] = false;
            // $input['tPagataParziale'] = false;
            // $input['tScaricata'] = false;
            // $input['tStampata'] = false;

            $response = $this->client->PasvElencoFatture($input);                                                   // scarico elenco fatture passive

            // dd(isset($response), 'STOP');

            $supplierNumber = 0;
            $invoiceNumber = 0;

            if(isset($response->Fattura)){
                if (is_array($response->Fattura)) {                                                                 // se ci sono più fatture passive da scaricare
                    foreach($response->Fattura as $item){
                        $checkPI = PassiveInvoice::where('sdi_code', $item->IdentificativoSdI)->first();
                        if($checkPI){ continue; }
                        $param['item'] = $item;

                        $i_input['Autenticazione'] = $this->getAutenticazione(null, $data['password']);
                        $i_input['IdentificativoSdI'] = $item->IdentificativoSdI;
                        // $i_input['IdentificativoSdI'] = '15082389451';

                        $i_response_pdf = $this->client->PasvDownloadPDF($i_input);                                 // recupero file PDF della fattura

                        $i_input['Unwrap'] = true;
                        $i_response_xml = $this->client->PasvDownload($i_input);                                    // recupero file XML della fattura

                        $param['filePath_xml'] = $this->savePassiveXML($i_response_xml->Nome, $i_response_xml->Contenuto); // salvo il file XML
                        $param['filePath_pdf'] = $this->savePassivePDF($i_response_pdf->Nome, $i_response_pdf->Contenuto); // salvo il file PDF

                        $param['content']  = $this->xmlToArray($i_response_xml->Contenuto);                         // creo l'array con i dati dell'xml della fattura

                        $newSupplier = $this->checkSupplier($param['content']['FatturaElettronicaHeader']);         // controllo e nel caso inserisco un nuovo fornitore, ritorno il fornitore della fattura
                        if($newSupplier['new']) $supplierNumber++;                                                  // se ho aggiunto il fornitore incremento il contatore dei fornitori
                        $param['supplier']  = $newSupplier['supplier'];

                        $passiveInvoice = $this->createPassiveInvoice($param);                                      // creo una nuova fattura passiva e ritorno la fattura creata
                        $param['passive_invoice']  = $passiveInvoice;

                        // Gestione allegati
                        if (isset($param['content']['FatturaElettronicaBody']['Allegati'])) {
                            $allegati = $param['content']['FatturaElettronicaBody']['Allegati'];

                            // Normalizzo in array (potrebbe essere singolo o multiplo)
                            if (!isset($allegati[0])) {
                                $allegati = [$allegati];
                            }

                            foreach ($allegati as $allegato) {
                                if (!empty($allegato['Attachment'])) {
                                    $nomeFile = $allegato['NomeAttachment'] ?? 'allegato_' . time() . '.' . ($allegato['FormatoAttachment'] ?? 'pdf');
                                    $contenutoBase64 = $allegato['Attachment'];

                                    // Salvo il file allegato
                                    $allegatoPath = $this->savePassiveAttachment($passiveInvoice, $nomeFile, $contenutoBase64);

                                    // Salvo il path dell'allegato nella fattura passiva
                                    $passiveInvoice->update([
                                        'attachments_path' => $allegatoPath,
                                    ]);
                                }
                            }
                        }

                        $detailsNumber = $this->createPassiveItems($param);                                         // creo i dettagli della fattura passiva

                        if(!$passiveInvoice->total){                                                                // controllo valore totale fattura passiva
                            $passiveInvoice->update(['total' => $passiveInvoice->passiveItems()->sum('total_price')]);
                        }

                        $invoiceNumber++;                                                                           // incremento il contatore di fatture passive

                        if(!$passiveInvoice->parent_id){                                                            // se non è una nota di credito creo la scadenza
                            $deadline = Deadline::create([
                                'company_id' => Filament::getTenant()->id,
                                'description' => 'Fattura numero ' . $passiveInvoice->number . ' da ' . $passiveInvoice->supplier->denomination,
                                'note' => null,
                                'date' => $passiveInvoice->payment_deadline,
                                'amount'  => $passiveInvoice->total,
                                'dispatched' => false
                            ]);
                        }
                    }
                } else {                                                                                            // se c'è una sola fattura passiva da scaricare
                    $item = $response->Fattura;
                    $checkPI = PassiveInvoice::where('sdi_code', $item->IdentificativoSdI)->first();
                    if(!$checkPI){
                        $param['item'] = $item;

                        $i_input['Autenticazione'] = $this->getAutenticazione(null, $data['password']);
                        $i_input['IdentificativoSdI'] = $item->IdentificativoSdI;
                        // $i_input['IdentificativoSdI'] = '15082389451';

                        $i_response_pdf = $this->client->PasvDownloadPDF($i_input);                                 // recupero file PDF della fattura

                        $i_input['Unwrap'] = true;
                        $i_response_xml = $this->client->PasvDownload($i_input);                                    // recupero file XML della fattura

                        $param['filePath_xml'] = $this->savePassiveXML($i_response_xml->Nome, $i_response_xml->Contenuto); // salvo il file XML
                        $param['filePath_pdf'] = $this->savePassivePDF($i_response_pdf->Nome, $i_response_pdf->Contenuto); // salvo il file PDF

                        $param['content']  = $this->xmlToArray($i_response_xml->Contenuto);                         // creo l'array con i dati dell'xml della fattura

                        $newSupplier = $this->checkSupplier($param['content']['FatturaElettronicaHeader']);         // controllo e nel caso inserisco un nuovo fornitore, ritorno il fornitore della fattura
                        if($newSupplier['new']) $supplierNumber++;                                                  // se ho aggiunto il fornitore incremento il contatore dei fornitori
                        $param['supplier']  = $newSupplier['supplier'];

                        $passiveInvoice = $this->createPassiveInvoice($param);                                      // creo una nuova fattura passiva e ritorno la fattura creata
                        $param['passive_invoice']  = $passiveInvoice;

                        // Gestione allegati
                        if (isset($param['content']['FatturaElettronicaBody']['Allegati'])) {
                            $allegati = $param['content']['FatturaElettronicaBody']['Allegati'];

                            // Normalizzo in array (potrebbe essere singolo o multiplo)
                            if (!isset($allegati[0])) {
                                $allegati = [$allegati];
                            }

                            foreach ($allegati as $allegato) {
                                if (!empty($allegato['Attachment'])) {
                                    $nomeFile = $allegato['NomeAttachment'] ?? 'allegato_' . time() . '.' . ($allegato['FormatoAttachment'] ?? 'pdf');
                                    $contenutoBase64 = $allegato['Attachment'];

                                    // Salvo il file allegato
                                    $allegatoPath = $this->savePassiveAttachment($passiveInvoice, $nomeFile, $contenutoBase64);

                                    // Salvo il path dell'allegato nella fattura passiva
                                    $passiveInvoice->update([
                                        'attachments_path' => $allegatoPath,
                                    ]);
                                }
                            }
                        }

                        $detailsNumber = $this->createPassiveItems($param);                                         // creo i dettagli della fattura passiva

                        if(!$passiveInvoice->total){                                                                // controllo valore totale fattura passiva
                            $passiveInvoice->update(['total' => $passiveInvoice->passiveItems()->sum('total_price')]);
                        }

                        $invoiceNumber++;                                                                           // incremento il contatore di fatture passive

                        if(!$passiveInvoice->parent_id){                                                            // se non è una nota di credito creo la scadenza
                            $deadline = Deadline::create([
                                'company_id' => Filament::getTenant()->id,
                                'description' => 'Fattura numero ' . $passiveInvoice->number . ' da ' . $passiveInvoice->supplier->denomination,
                                'note' => null,
                                'date' => $passiveInvoice->payment_deadline,
                                'amount'  => $passiveInvoice->total,
                                'dispatched' => false
                            ]);
                        }
                    }
                }
            }

            $download = PassiveDownload::create([
                'company_id' => Filament::getTenant()->id,
                'date' => date('Y-m-d', strtotime('-1 day')),
                'new_suppliers' => $supplierNumber,
                'new_invoices' => $invoiceNumber
            ]);

            // dd($download);

            // dd('STOP');

            DB::commit();

            return $download;
        } catch (SoapFault $soapEx) {
            DB::rollBack();
            throw new Exception('Errore SOAP: ' . $soapEx->getMessage());
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception('Errore: ' . $ex->getMessage());
        }
    }
}
