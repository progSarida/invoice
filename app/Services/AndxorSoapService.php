<?php

namespace App\Services;

use App\Enums\SdiStatus;
use App\Enums\WithholdingType;
use App\Models\Invoice;
use App\Models\State;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

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

    private function getAutenticazione(Invoice $invoice, string $password): ?array
    {
        $idPaeseCedente = $invoice->company->state_id && State::find($invoice->company->state_id) && preg_match('/^[A-Z]{2}$/', State::find($invoice->company->state_id)->alpha2) ? State::find($invoice->company->state_id)->alpha2 : 'IT';
        $idCodice = $invoice->company->vat_number ?? $invoice->company->taxnumber;
        if ($idPaeseCedente && preg_match('/^[A-Za-z0-9]{1,28}$/', $idCodice)) {
            return [
                'Cedente' => [
                    'IdPaese' => $idPaeseCedente,
                    'IdCodice' => $idCodice,
                    // 'IdCodice_' => '01338160995',
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

            // Aggiorna l'Invoice con il progressivo di invio
            $invoice->update([
                'sdi_status' => SdiStatus::INVIATA->value ?? null,
                'sdi_code' => $response->ProgressivoInvio ?? null,
                'sdi_date' => Carbon::today()->format('Y-m-d')
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
}
