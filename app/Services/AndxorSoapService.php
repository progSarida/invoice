<?php

namespace App\Services;

use App\Enums\FundType;
use Exception;
use SoapFault;
use SoapClient;
use Carbon\Carbon;
use App\Models\State;
use App\Models\Company;
use App\Models\Invoice;
use App\Enums\SdiStatus;
use App\Enums\WithholdingType;
use App\Models\Deadline;
use App\Models\PassiveDownload;
use App\Models\PassiveInvoice;
use App\Models\PassiveItem;
use App\Models\Supplier;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AndxorSoapService
{
    protected $client;

    public function __construct()
    {
        $wsdl = 'https://tinv-test.andxor.it/userServices?wsdl';
        $options = [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
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

    private function validateIdFiscaleIVA(?string $vatNumber, ?string $taxNumber, string $idPaese): ?array
    {
        $idCodice = $vatNumber ?? $taxNumber;
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
                'IdFiscaleIVA' => $this->validateIdFiscaleIVA($invoice->company->vat_number, $invoice->company->tax_number, $idPaeseCedente),
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
                'IdFiscaleIVA' => $this->validateIdFiscaleIVA($invoice->client->vat_code, $invoice->client->tax_code, $idPaeseCommittente),
                'CodiceFiscale' => $invoice->client->tax_code ?? null,
                'Anagrafica' => [
                    'Denominazione' => $invoice->client->denomination,
                ],
            ],
            'Sede' => array_filter([
                'Indirizzo' => $invoice->client->address ?? '',
                'NumeroCivico' => $invoice->client->address_number && preg_match('/^[A-Za-z0-9]{1,8}$/', $invoice->client->address_number) ? $invoice->client->address_number : null,
                'CAP' => $invoice->client->city->zip_code ?? '',
                'Comune' => $invoice->client->city->name ?? '',
                'Provincia' => $invoice->client->city->province->code ?? '',
                'Nazione' => $idPaeseCommittente,
            ], fn($value) => !is_null($value) && $value !== ''),
        ];
    }

    private function getDatiGeneraliDocumento(Invoice $invoice, array $withholdings, array $funds): ?array
    {
        return [
            'TipoDocumento' => $invoice->docType->name && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->docType->name) ? $invoice->docType->name : 'TD01',
            'Divisa' => $invoice->divisa ?? 'EUR',
            'Data' => $invoice->invoice_date->format('Y-m-d'),
            'Numero' => $invoice->getNewInvoiceNumber(),
            'ImportoTotaleDocumento' => sprintf("%.2f", (float) ($invoice->total ?? 0.00)),
            'DatiRitenuta' => array_map(function ($withholding) {
                return [
                    'TipoRitenuta' => $withholding['tipo_ritenuta'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['tipo_ritenuta']) ? $withholding['tipo_ritenuta'] : 'RT01',
                    'ImportoRitenuta' => sprintf("%.2f", (float) ($withholding['importo_ritenuta'] ?? 0.00)),
                    'AliquotaRitenuta' => sprintf("%.2f", (float) ($withholding['aliquota_ritenuta'] ?? 20.00)),
                    'CausalePagamento' => $withholding['causale_pagamento'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['causale_pagamento']) ? $withholding['causale_pagamento'] : 'A',
                ];
            }, $withholdings),
            'DatiBollo' => [
                'BolloVirtuale' => $invoice->company->stampDuty->virtual_stamp ? 'SI' : '',
                'ImportoBollo' => $invoice->company->stampDuty->virtual_stamp ? sprintf("%.2f", 2.00) : '',
            ],
            'DatiCassaPrevidenziale' => array_map(function ($fund) {
                return array_filter([
                    'TipoCassa' => $fund['fund_code'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $fund['fund_code']) ? $fund['fund_code'] : 'TC02',
                    'AlCassa' => sprintf("%.2f", (float) ($fund['rate'])),
                    'ImportoContributoCassa' => sprintf("%.2f", (float) ($fund['amount'])),
                    'ImponibileCassa' => sprintf("%.2f", (float) ($fund['taxable_base'])),
                    'AliquotaIVA' => isset($fund['%']) && $fund['%'] !== null ? sprintf("%.2f", (float) $fund['%']) : "0.00",
                    'Ritenuta' => !empty($fund['withholding']) ? 'SI' : null,
                    'Natura' => $fund['%'] == 'N1' ? 'N1' : null,
                ], fn($value) => !is_null($value) && $value !== '');
            }, $funds),
        ];
    }

    private function getDatiOrdineAcquisto(Invoice $invoice): ?array
    {
        return $invoice->contract ? array_filter([
            array_filter([
                'IdDocumento' => $invoice->contract->lastDetail->number && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->contract->lastDetail->number) ? $invoice->contract->lastDetail->number : null,
                'Data' => $invoice->contract->lastDetail->date ?? null,
                'CodiceCUP' => $invoice->contract->lastDetail->cup_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract->lastDetail->cup_code) ? $invoice->contract->lastDetail->cup_code : null,
                'CodiceCIG' => $invoice->contract->lastDetail->cig_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract->lastDetail->cig_code) ? $invoice->contract->lastDetail->cig_code : null,
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
            'DettaglioLinee' => $invoice->invoiceItems->map(function ($item, $index) {                      // prima di ->map() inserire ->where('auto', false)
                return [
                    'NumeroLinea' => $index + 1,
                    'Descrizione' => $item->description ?? 'Servizio',
                    'Quantita' => sprintf("%.2f", (float) ($item->quantity ?? 1.00)),
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
                    'EsigibilitaIVA' => in_array($vat['norm'][0] ?? 'I', ['D', 'I', 'S']) ? $vat['norm'][0] : 'I',
                    'RiferimentoNormativo' => $vat['free'] ? $vat['norm'] : null,
                ];
            }, $invoice->updateResume($invoice->vatResume(), $invoice->getFundBreakdown()))),
        ];
    }

    private function getDatiPagamento(Invoice $invoice): ?array
    {
        return [
            [
                'CondizioniPagamento' => $this->mapPaymentTypeToCondizioniPagamento($invoice->payment_type->value ?? 'TP02'),
                'DettaglioPagamento' => [
                    [
                        'ModalitaPagamento' => $invoice->payment_type->getCode() ?? 'MP05',
                        'DataScadenzaPagamento' => $invoice->invoice_date->addDays($invoice->payment_days ?? 30)->format('Y-m-d'),
                        'ImportoPagamento' => sprintf("%.2f", (float) ($invoice->total ?? 0.00)),
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
            if (!empty($codiceDestinatario)) {
                $payload['CodiceDestinatario'] = $this->validateCodiceDestinatario($codiceDestinatario);
            } else {
                $payload['PECDestinatario'] = $invoice->client->pec;
            }
            $payload['OverrideCedente'] = $this->getOverrideCedente($invoice);
            $payload['CessionarioCommittente'] = $this->getCessionarioCommittente($invoice);
            $payload['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento'] = $this->getDatiGeneraliDocumento($invoice, $withholdings, $funds);
            $payload['FatturaElettronicaBody']['DatiGenerali']['DatiOrdineAcquisto'] = $this->getDatiOrdineAcquisto($invoice);
            $payload['FatturaElettronicaBody']['DatiGenerali']['DatiDDT'] = $this->getDatiDDT($invoice);
            $payload['FatturaElettronicaBody']['DatiBeniServizi'] = $this->getDatiBeniServizi($invoice);
            $payload['FatturaElettronicaBody']['DatiPagamento'] = $this->getDatiPagamento($invoice);

            $payload_ = [
                'Autenticazione' => [
                    'Cedente' => [
                        'IdPaese' => $idPaeseCedente,
                        'IdCodice' => $invoice->company->vat_number ?? $invoice->company->taxnumber,
                        'IdCodice_' => '01338160995',
                    ],
                    'Password' => $password,
                ],
                'CodiceDestinatario' => $this->validateCodiceDestinatario($invoice->client->ipa_code ?? $invoice->contract->office_code ?? '0000000'),
                'PECDestinatario' => $invoice->client->pec,
                'OverrideCedente' => [
                    'DatiAnagrafici' => array_filter([
                        'IdFiscaleIVA' => $this->validateIdFiscaleIVA($invoice->company->vat_number, $invoice->company->tax_number, $idPaeseCedente),
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
                ],
                'CessionarioCommittente' => [
                    'DatiAnagrafici' => [
                        'IdFiscaleIVA' => $this->validateIdFiscaleIVA($invoice->client->vat_code, $invoice->client->tax_code, $idPaeseCommittente),
                        'CodiceFiscale' => $invoice->client->tax_code ?? null,
                        'Anagrafica' => [
                            'Denominazione' => $invoice->client->denomination,
                        ],
                    ],
                    'Sede' => array_filter([
                        'Indirizzo' => $invoice->client->address ?? '',
                        'NumeroCivico' => $invoice->client->address_number && preg_match('/^[A-Za-z0-9]{1,8}$/', $invoice->client->address_number) ? $invoice->client->address_number : null,
                        'CAP' => $invoice->client->city->zip_code ?? '',
                        'Comune' => $invoice->client->city->name ?? '',
                        'Provincia' => $invoice->client->city->province->code ?? '',
                        'Nazione' => $idPaeseCommittente,
                    ], fn($value) => !is_null($value) && $value !== ''),
                ],
                'FatturaElettronicaBody' => [
                    'DatiGenerali' => [
                        'DatiGeneraliDocumento' => [
                            'TipoDocumento' => $invoice->docType->name && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->docType->name) ? $invoice->docType->name : 'TD01',
                            'Divisa' => $invoice->divisa ?? 'EUR',
                            'Data' => $invoice->invoice_date->format('Y-m-d'),
                            'Numero' => $invoice->getNewInvoiceNumber(),
                            'ImportoTotaleDocumento' => sprintf("%.2f", (float) ($invoice->total ?? 0.00)),
                            'DatiRitenuta' => array_map(function ($withholding) {
                                return [
                                    'TipoRitenuta' => $withholding['tipo_ritenuta'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['tipo_ritenuta']) ? $withholding['tipo_ritenuta'] : 'RT01',
                                    'ImportoRitenuta' => sprintf("%.2f", (float) ($withholding['importo_ritenuta'] ?? 0.00)),
                                    'AliquotaRitenuta' => sprintf("%.2f", (float) ($withholding['aliquota_ritenuta'] ?? 20.00)),
                                    'CausalePagamento' => $withholding['causale_pagamento'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $withholding['causale_pagamento']) ? $withholding['causale_pagamento'] : 'A',
                                ];
                            }, $withholdings),
                            'DatiBollo' => [
                                'BolloVirtuale' => $invoice->company->stampDuty->virtual_stamp ? 'SI' : '',
                                'ImportoBollo' => $invoice->company->stampDuty->virtual_stamp ? sprintf("%.2f", 2.00) : '',
                            ],
                            'DatiCassaPrevidenziale' => array_map(function ($fund) {
                                return array_filter([
                                    'TipoCassa' => $fund['fund_code'] && preg_match('/^[A-Za-z0-9]{1,20}$/', $fund['fund_code']) ? $fund['fund_code'] : 'TC02',
                                    'AlCassa' => sprintf("%.2f", (float) ($fund['rate'])),
                                    'ImportoContributoCassa' => sprintf("%.2f", (float) ($fund['amount'])),
                                    'ImponibileCassa' => sprintf("%.2f", (float) ($fund['taxable_base'])),
                                    'AliquotaIVA' => isset($fund['%']) && $fund['%'] !== null ? sprintf("%.2f", (float) $fund['%']) : "0.00",
                                    'Ritenuta' => !empty($fund['withholding']) ? 'SI' : null,
                                    'Natura' => $fund['%'] == 'N1' ? 'N1' : null,
                                ], fn($value) => !is_null($value) && $value !== '');
                            }, $funds),
                        ],
                        'DatiOrdineAcquisto' => $invoice->contract ? array_filter([
                            array_filter([
                                'IdDocumento' => $invoice->contract->lastDetail->number && preg_match('/^[A-Za-z0-9]{1,20}$/', $invoice->contract->lastDetail->number) ? $invoice->contract->lastDetail->number : null,
                                'Data' => $invoice->contract->lastDetail->date ?? null,
                                'CodiceCUP' => $invoice->contract->lastDetail->cup_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract->lastDetail->cup_code) ? $invoice->contract->lastDetail->cup_code : null,
                                'CodiceCIG' => $invoice->contract->lastDetail->cig_code && preg_match('/^[A-Za-z0-9]{1,15}$/', $invoice->contract->lastDetail->cig_code) ? $invoice->contract->lastDetail->cig_code : null,
                            ], fn($value) => !is_null($value) && $value !== '')
                        ], fn($value) => !empty($value)) : [],
                        'DatiDDT' => $invoice->delivery_note ? [
                            [
                                'NumeroDDT' => $invoice->delivery_note,
                                'DataDDT' => $invoice->delivery_date ?? $invoice->invoice_date->format('Y-m-d'),
                            ],
                        ] : [],
                    ],
                    'DatiBeniServizi' => [
                        'DettaglioLinee' => $invoice->invoiceItems->map(function ($item, $index) {
                            return [
                                'NumeroLinea' => $index + 1,
                                'Descrizione' => $item->description ?? 'Servizio',
                                'Quantita' => sprintf("%.2f", (float) ($item->quantity ?? 1.00)),
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
                                'EsigibilitaIVA' => in_array($vat['norm'][0] ?? 'I', ['D', 'I', 'S']) ? $vat['norm'][0] : 'I',
                                'RiferimentoNormativo' => $vat['free'] ? $vat['norm'] : null,
                            ];
                        }, $invoice->updateResume($invoice->vatResume(), $invoice->getFundBreakdown()))),
                    ],
                    'DatiPagamento' => [
                        [
                            'CondizioniPagamento' => $this->mapPaymentTypeToCondizioniPagamento($invoice->payment_type->value ?? 'TP02'),
                            'DettaglioPagamento' => [
                                [
                                    'ModalitaPagamento' => $invoice->payment_type->getCode() ?? 'MP05',
                                    'DataScadenzaPagamento' => $invoice->invoice_date->addDays($invoice->payment_days ?? 30)->format('Y-m-d'),
                                    'ImportoPagamento' => sprintf("%.2f", (float) ($invoice->total ?? 0.00)),
                                    'IBAN' => $invoice->bankAccount->iban ?? null,
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // dd(json_encode($payload_, JSON_PRETTY_PRINT));

            Log::debug('Payload SOAP: ' . json_encode($payload, JSON_PRETTY_PRINT));

            // Esegui la chiamata SOAP
            $response = $this->client->InviaFattura($payload);

            // dd($response);

            $input['Autenticazione'] = $this->getAutenticazione($invoice, $password);
            $input['ProgressivoInvio'] = $response->ProgressivoInvio ?? null;

            $response_s = $this->client->Stato($input);

            // dd($response_s);

            $date = explode("T", $response_s->DataOraCreazione);

            // Aggiorna stato, codici e data di invio della fattura
            $invoice->update([
                'service_code' => $response->ProgressivoInvio ?? null,
                'sdi_code' => $response_s->IdSdI ?? null,
                'sdi_status' => $this->translateStatus($response_s->Stato),
                'sdi_date' => $date[0]
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
        $input['ProgressivoInvio'] = $invoice->service_code ?? null;

        $response = $this->client->Stato($input);

        // dd($response);

        $date = explode("T", $response->DataOraCreazione);                                              // la data deve essere in base allo stato?
        // $date = explode("T", $this->getDate($response));

        // Aggiorna stato e data modifica stato della fattura
        $invoice->update([
            'sdi_status' => $this->translateStatus($response->Stato),
            'sdi_date' => $date[0]
        ]);

        return $response;
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

    private function saveXML(string $filename, string $content): string
    {
        // Definisco il percorso relativo per il file XML
        $relativePath = 'passive_invoices/xml_files/' . $filename;

        // Salvo il file usando il disco 'public'
        if (Storage::disk('public')->put($relativePath, $content)) {
            return $relativePath; // Restituisco il percorso relativo
        } else {
            throw new \Exception("Errore durante il salvataggio del file XML: $filename");
        }
    }

    private function savePDF(string $filename, string $content): string
    {
        // Definisco il percorso relativo per il file PDF
        $relativePath = 'passive_invoices/pdf_files/' . $filename;

        // Salvo il file usando il disco 'public'
        if (Storage::disk('public')->put($relativePath, $content)) {
            return $relativePath; // Restituisco il percorso relativo
        } else {
            throw new \Exception("Errore durante il salvataggio del file PDF: $filename");
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
        $vatCode = data_get($param, 'CedentePrestatore.DatiAnagrafici.IdFiscaleIVA.IdCodice');
        $taxCode1 = data_get($param, 'CedentePrestatore.DatiAnagrafici.IdFiscaleIVA.IdCodice'); // può essere lo stesso del precedente
        $taxCode2 = data_get($param, 'CedentePrestatore.DatiAnagrafici.CodiceFiscale');
        if ($vatCode) {
            $query->orWhere('vat_code', $vatCode);
        }
        if ($taxCode1) {
            $query->orWhere('tax_code', $taxCode1);
        }
        if ($taxCode2) {
            $query->orWhere('tax_code', $taxCode2);
        }
        $supplier = $query->first();

        // dd($supplier);

        $cedente = $param['CedentePrestatore'];

        if(!$supplier){
            $output['new'] = true;
            $data = [
                'company_id' => Filament::getTenant()->id,

                'denomination' => $cedente['DatiAnagrafici']['Anagrafica']['Denominazione'] ?? $cedente['DatiAnagrafici']['Anagrafica']['Cognome'] . $cedente['DatiAnagrafici']['Anagrafica']['Nome'],
                'tax_code' => $cedente['DatiAnagrafici']['CodiceFiscale'] ?? null,
                'vat_code' => $cedente['DatiAnagrafici']['IdFiscaleIVA']['IdCodice'] ?? null,

                'address' => $cedente['Sede']['Indirizzo'] ?? null,
                'civic_number' => $cedente['Sede']['NumeroCivico'] ?? null,
                'zip_code' => $cedente['Sede']['CAP'] ?? null,
                'city' => $cedente['Sede']['Comune'] ?? null,
                'province' => $cedente['Sede']['Provincia'] ?? null,
                'country' => $cedente['Sede']['Nazione'] ?? null,

                'rea_office' => $cedente['IscrizioneREA']['Ufficio'] ?? null,
                'rea_number' => $cedente['IscrizioneREA']['NumeroREA'] ?? null,
                'capital' => $cedente['IscrizioneREA']['CapitaleSociale'] ?? null,
                'sole_share' => $cedente['IscrizioneREA']['SocioUnico'] ?? null,
                'liquidation_status' => $cedente['IscrizioneREA']['StatoLiquidazione'] ?? null,

                'phone' => $cedente['Contatti']['Telefono'] ?? null,
                'fax' => $cedente['Contatti']['Fax'] ?? null,
                'email' => $cedente['Contatti']['Email'] ?? null,
                'pec' => null
            ];

            $supplier = Supplier::create($data);
        }

        // dd($supplier);

        $output['supplier'] = $supplier;

        // dd($output);

        return $output;
    }

    private function createPassiveInvoice(array $param): PassiveInvoice
    {
        $supplier = $param['supplier'];                                                                                 // fornitore
        $xml = $param['content'];                                                                                       // array xml
        $item = $param['item'];

        // dd($xml);

        $rawCausale = $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['Causale'] ?? null;

        $data = [
                'company_id' => Filament::getTenant()->id,
                'supplier_id' => $supplier->id,
                'doc_type' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['TipoDocumento'] ?? null,
                'invoice_date' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['Data'] ?? null,
                'number' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['Numero'] ?? null,
                'description' => is_array($rawCausale) ? implode('; ', $rawCausale) : $rawCausale,
                'total' => $xml['FatturaElettronicaBody']['DatiGenerali']['DatiGeneraliDocumento']['ImportoTotaleDocumento'] ?? null,
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
                        'total_price' => $withholding['ImportoRitenuta'] ?? null,
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
                'DataInizio' => $dataInizio, // Formato: YYYY-MM-DD
                'DataOraInizio' => $dataInizio . 'T00:00:00', // Formato: YYYY-MM-DDThh:mm:ss
                'DataFine' => $dataFine, // Formato: YYYY-MM-DD
                'DataOraFine' => $dataFine . 'T23:59:59', // Formato: YYYY-MM-DDThh:mm:ss
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

            $response = $this->client->PasvElencoFatture($input);                                           // scarico elenco fatture passive

            // dd($response->Fattura);

            $supplierNumber = 0;
            $invoiceNumber = 0;

            if (is_array($response->Fattura)) {                                                                 // se ci sono più fatture passive da scaricare
                foreach($response->Fattura as $item){
                    $param['item'] = $item;

                    $i_input['Autenticazione'] = $this->getAutenticazione(null, $data['password']);
                    $i_input['IdentificativoSdI'] = $item->IdentificativoSdI;
                    // $i_input['IdentificativoSdI'] = '15082389451';

                    $i_response_pdf = $this->client->PasvDownloadPDF($i_input);                                 // recupero file PDF della fattura

                    $i_input['Unwrap'] = true;
                    $i_response_xml = $this->client->PasvDownload($i_input);                                    // recupero file XML della fattura

                    $param['filePath_xml'] = $this->saveXML($i_response_xml->Nome, $i_response_xml->Contenuto); // salvo il file XML
                    $param['filePath_pdf'] = $this->savePDF($i_response_pdf->Nome, $i_response_pdf->Contenuto); // salvo il file PDF

                    $param['content']  = $this->xmlToArray($i_response_xml->Contenuto);                         // creo l'array con i dati dell'xml della fattura

                    $newSupplier = $this->checkSupplier($param['content']['FatturaElettronicaHeader']);         // controllo e nel caso inserisco un nuovo fornitore, ritorno il fornitore della fattura
                    if($newSupplier['new']) $supplierNumber++;                                                  // se ho aggiunto il fornitore incremento il contatore dei fornitori
                    $param['supplier']  = $newSupplier['supplier'];

                    $passiveInvoice = $this->createPassiveInvoice($param);                                      // creo una nuova fattura passiva e ritorno la fattura creata
                    $param['passive_invoice']  = $passiveInvoice;

                    $detailsNumber = $this->createPassiveItems($param);                                         // creo i dettagli della fattura passiva

                    $invoiceNumber++;                                                                           // incremento il contatore di fatture passive

                    $deadline = Deadline::create([
                        'company_id' => Filament::getTenant()->id,
                        'description' => 'Fattura numero ' . $passiveInvoice->number . ' da ' . $passiveInvoice->supplier->denomination,
                        'note' => null,
                        'date' => $passiveInvoice->payment_deadline,
                        'amount'  => $passiveInvoice->total,
                        'dispatched' => false
                    ]);
                }
            } else {                                                                                            // se c'è una sola fattura passiva da scaricare
                $item = $response->Fattura;
                $param['item'] = $item;

                    $i_input['Autenticazione'] = $this->getAutenticazione(null, $data['password']);
                    $i_input['IdentificativoSdI'] = $item->IdentificativoSdI;
                    // $i_input['IdentificativoSdI'] = '15082389451';

                    $i_response_pdf = $this->client->PasvDownloadPDF($i_input);                                 // recupero file PDF della fattura

                    $i_input['Unwrap'] = true;
                    $i_response_xml = $this->client->PasvDownload($i_input);                                    // recupero file XML della fattura

                    $param['filePath_xml'] = $this->saveXML($i_response_xml->Nome, $i_response_xml->Contenuto); // salvo il file XML
                    $param['filePath_pdf'] = $this->savePDF($i_response_pdf->Nome, $i_response_pdf->Contenuto); // salvo il file PDF

                    $param['content']  = $this->xmlToArray($i_response_xml->Contenuto);                         // creo l'array con i dati dell'xml della fattura

                    $newSupplier = $this->checkSupplier($param['content']['FatturaElettronicaHeader']);         // controllo e nel caso inserisco un nuovo fornitore, ritorno il fornitore della fattura
                    if($newSupplier['new']) $supplierNumber++;                                                  // se ho aggiunto il fornitore incremento il contatore dei fornitori
                    $param['supplier']  = $newSupplier['supplier'];

                    $passiveInvoice = $this->createPassiveInvoice($param);                                      // creo una nuova fattura passiva e ritorno la fattura creata
                    $param['passive_invoice']  = $passiveInvoice;

                    $detailsNumber = $this->createPassiveItems($param);                                         // creo i dettagli della fattura passiva

                    $invoiceNumber++;                                                                           // incremento il contatore di fatture passive

                    $deadline = Deadline::create([
                        'company_id' => Filament::getTenant()->id,
                        'description' => 'Fattura numero ' . $passiveInvoice->number . ' da ' . $passiveInvoice->supplier->denomination,
                        'note' => null,
                        'date' => $passiveInvoice->payment_deadline,
                        'amount'  => $passiveInvoice->total,
                        'dispatched' => false
                    ]);
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
        } catch (\SoapFault $soapEx) {
            DB::rollBack();
            throw new \Exception('Errore SOAP: ' . $soapEx->getMessage());
        } catch (\Exception $ex) {
            DB::rollBack();
            throw new \Exception('Errore: ' . $ex->getMessage());
        }
    }
}
