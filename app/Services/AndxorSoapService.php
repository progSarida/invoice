<?php

namespace App\Services;

use App\Enums\SdiStatus;
use App\Enums\WithholdingType;
use App\Models\Invoice;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

class AndxorSoapService
{
    protected $client;

    /* Crea una nuova istanza */
    public function __construct()
    {
        $wsdl = 'https://tinv-test.andxor.it/userServices?wsdl';
        $options = [
            'trace' => true,                                                    // Abilita il tracciamento per il debug
            'exceptions' => true,                                               // Abilita le eccezioni
            'cache_wsdl' => WSDL_CACHE_NONE,                                    // Disabilita la cache per test
            'soap_version' => SOAP_1_1,                                         // Versione SOAP
        ];

        try {
            $this->client = new SoapClient($wsdl, $options);
        } catch (Exception $e) {
            throw new Exception('Errore nella connessione al servizio SOAP: ' . $e->getMessage());
        }
    }
    
    public function sendInvoice(Invoice $invoice, string $password)
    {
        try {
            $withholdings = [];
            foreach($invoice->company->withholdings as $item){
                if(in_array($item->withholding_type, [WithholdingType::RT01, WithholdingType::RT02]))
                    $withholdings[] = $item;
            }
            // Mappa i dati dell'Invoice al payload SOAP
            $payload = [
                'Autenticazione' => [
                    'Cedente' => [
                        'IdPaese' => $invoice->company->country_code ?? 'IT',                                                                   // MANCA, mettere nei dati di company
                        // 'IdCodice' => $invoice->company->vat_number ?? $invoice->company->tax_code,                                             // Partita IVA / Codice  Fiscale
                        'IdCodice' => '01338160995',                                                                                            // TEST
                    ],
                    'Password' => $password,                                                                                                    // 
                    'CodiceDestinatario' => $invoice->client->ipa_code ?? $invoice->contract->office_code,                                      // Codice IPA cliente, se nullo codice IPA ufficio in contratto
                    'PECDestinatario' => $invoice->client->pec,                                                                                 // Alternativa, se necessario
                ],
                'OverrideCedente' => [
                    'DatiAnagrafici' => [
                        'IdFiscaleIVA' => [
                            'IdPaese' => $invoice->company->country_code ?? 'IT',                                                               // MANCA, mettere nei dati di company
                            'IdCodice' => $invoice->company->vat_number ?? $invoice->company->tax_number,                                       // Partita IVA / Codice  Fiscale
                        ],
                        'CodiceFiscale' => $invoice->company->tax_number ?? null,                                                               // 
                        'Anagrafica' => [
                            'Denominazione' => $invoice->company->name,                                                                         // 
                        ],  
                        'RegimeFiscale' => $invoice->company->fiscalProfile->tax_regime->getCode() ?? '',                                       // 
                    ],
                    'Sede' => [
                        'Indirizzo' => $invoice->company->address ?? '',                                                                        // 
                        'NumeroCivico' => $invoice->company->address_number ?? '',                                                              // 
                        'CAP' => $invoice->company->city->zip_code ?? '',                                                                       // 
                        'Comune' => $invoice->company->city->name ?? '',                                                                        // 
                        'Provincia' => $invoice->company->city->province->code ?? '',                                                           // 
                        'Nazione' => $invoice->company->country_code ?? 'IT',                                                                   // MANCA
                    ],
                    'Contatti' => [
                        'Telefono' => $invoice->company->phone ?? '',                                                                           // 
                        'Email' => $invoice->company->email ?? '',                                                                              // 
                    ],
                ],
                'CessionarioCommittente' => [
                    'DatiAnagrafici' => [
                        'IdFiscaleIVA' => [
                            'IdPaese' => $invoice->client->country_code ?? 'IT',                                                                // MANCA
                            'IdCodice' => $invoice->client->vat_number ?? $invoice->client->tax_number,                                         // Partita IVA / Codice Fiscale
                        ],
                        'CodiceFiscale' => $invoice->client->tax_number ?? null,                                                                // Codice Fiscale
                        'Anagrafica' => [
                            'Denominazione' => $invoice->client->denomination,                                                                  // 
                            // Per persone fisiche: 'Nome' => $invoice->client->first_name, 'Cognome' => $invoice->client->last_name
                        ],
                    ],
                    'Sede' => [
                        'Indirizzo' => $invoice->client->address ?? '',                                                                         // 
                        'CAP' => $invoice->client->city->zip_code ?? '',                                                                        // 
                        'Comune' => $invoice->client->city->name ?? '',                                                                         // 
                        'Provincia' => $invoice->client->city->province->codde ?? '',                                                           // 
                        'Nazione' => $invoice->client->country_code ?? 'IT',                                                                    // MANCA
                    ],
                ],
                'FatturaElettronicaBody' => [
                    'DatiGenerali' => [
                        'DatiGeneraliDocumento' => [
                            'TipoDocumento' => $invoice->docType->name ?? $invoice->docType->name ?? '',                                        // 
                            'Divisa' => $invoice->divisa ?? 'EUR',                                                                              // 
                            'Data' => $invoice->invoice_date->format('Y-m-d'),                                                                  // 
                            'Numero' => $invoice->getNewInvoiceNumber(),                                                                        // 
                            'ImportoTotaleDocumento' => $invoice->total ?? 0.00,                                                                // 
                            'DatiRitenuta' => array_map(function ($withholding) {
                                return [
                                    'TipoRitenuta' => $withholding['tipo_ritenuta'] ?? 'RT01',                                                  // 
                                    'ImportoRitenuta' => $withholding['importo_ritenuta'] ?? 0.00,                                              // 
                                    'AliquotaRitenuta' => $withholding['aliquota_ritenuta'] ?? 20.00,                                           // 
                                    'CausalePagamento' => $withholding['causale_pagamento'] ?? 'A',                                             // 
                                ];
                            }, $withholdings ?? []),
                            'DatiBollo' => [
                                'BolloVirtuale' => $invoice->company->stampDuty->virtual_stamp ? 'SI' : '',                                     // 
                                'ImportoBollo' => $invoice->company->stampDuty->virtual_stamp ? 2.00 : '',                                      // 
                            ],
                            'DatiCassaPrevidenziale' => array_map(function ($fund) {
                                return [
                                    'TipoCassa' => $fund['fund_code'],                                                                          // 
                                    'AlCassa' => $fund['rate'],                                                                                 // 
                                    'ImportoContributoCassa' => $fund['amount'],                                                                // 
                                    'ImponibileCassa' => $fund['taxable_base'],                                                                 // 
                                    'AliquotaIVA' => $fund['%'] == 'N1' ? 0.00 : $fund['%'],                                                    // 
                                    'Ritenuta' => !empty($fund['withholding']) ? 'SI' : null,                                                   // 
                                    'Natura' => $fund['%'] == 'N1' ? 'N1' : null,                                                               // 
                                ];
                            }, $invoice->getFundBreakdown()),
                        ],
                        'DatiOrdineAcquisto' => $invoice->contract ? [                                                                          // 
                            [
                                'IdDocumento' => $invoice->contract->number ?? null,                                                            // 
                                'Data' => $invoice->contract->date ?? null,                                                                     // 
                                'CodiceCUP' => $invoice->contract->cup_code ?? null,                                                            // 
                                'CodiceCIG' => $invoice->contract->cig_code ?? null,                                                            // 
                            ],
                        ] : [],
                        'DatiDDT' => $invoice->delivery_note ? [                                                                                // 
                            [
                                'NumeroDDT' => $invoice->delivery_note,                                                                         // 
                                'DataDDT' => $invoice->delivery_date ?? $invoice->invoice_date->format('Y-m-d'),                                // 
                            ],
                        ] : [],
                    ],
                    'DatiBeniServizi' => [
                        'DettaglioLinee' => $invoice->invoiceItems->map(function ($item, $index) {                                              // 
                            return [
                                'NumeroLinea' => $index + 1,                                                                                    // 
                                'Descrizione' => $item->description ?? 'Servizio',                                                              // 
                                'Quantita' => $item->quantity ?? 1.00,                                                                          // 
                                'PrezzoUnitario' => $item->unit_price ?? $item->amount,                                                         // 
                                'PrezzoTotale' => $item->amount,                                                                                // 
                                'AliquotaIVA' => $item->vat_code_type->getRate() == '0' ? 0.00 : $item->vat_code_type->getRate(),               // 
                                'Natura' => $item->vat_code_type->getRate() == '0' ? $item->vat_code_type->getCode() : null,                    // 
                            ];
                        })->toArray(),
                        'DatiRiepilogo' => array_map(function ($vat) {
                            return [
                                'AliquotaIVA' => $vat['%'] == 'N1' ? 0.00 : $vat['%'],                                                          // 
                                'Natura' => $vat['%'] == 'N1' ? 'N1' : null,                                                                    // 
                                'ImponibileImporto' => $vat['taxable'],                                                                         // 
                                'Imposta' => $vat['vat'],                                                                                       // 
                                'EsigibilitaIVA' => $vat['norm'][0] ?? 'I', // Primo carattere di 'norm' (es. 'S', 'D', 'I')                    // 
                                'RiferimentoNormativo' => $vat['free'] ? $vat['norm'] : null,                                                   // 
                            ];
                        }, $invoice->updateResume($invoice->vatResume(), $invoice->getFundBreakdown())),
                    ],
                    'DatiPagamento' => [
                        [
                            'CondizioniPagamento' => $invoice->payment_type->value ?? 'TP02',                                                   // 
                            'DettaglioPagamento' => [
                                [
                                    'ModalitaPagamento' => $invoice->bankAccount->payment_method ?? 'MP05',                                     // Bonifico
                                    'DataScadenzaPagamento' => $invoice->invoice_date->addDays($invoice->payment_days ?? 30)->format('Y-m-d'),  // 
                                    'ImportoPagamento' => $invoice->total ?? 0.00,                                                              // 
                                    'IBAN' => $invoice->bankAccount->iban ?? null,                                                              // 
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // Esegui la chiamata SOAP
            $response = $this->client->InviaFattura(['parametersIn' => $payload]);

            // Aggiorna l'Invoice con il progressivo di invio
            $invoice->update([
                // 'progressivo_invio' => $response->parametersOut->ProgressivoInvio,                                                              // MANCA
                'sdi_status' => SdiStatus::INVIATA,                                                                                             // 
            ]);

            // Log della richiesta e risposta per debug
            Log::debug('Richiesta SOAP: ' . $this->client->getLastRequest());
            Log::debug('Risposta SOAP: ' . $this->client->getLastResponse());

            return $response->parametersOut;
        } catch (SoapFault $fault) {
            Log::error('Errore SOAP: ' . $fault->faultcode . ' - ' . $fault->faultstring);
            throw new Exception('Errore SOAP: ' . $fault->faultstring, 0, $fault);
        } catch (Exception $e) {
            Log::error('Errore generico: ' . $e->getMessage());
            throw new Exception('Errore generico: ' . $e->getMessage());
        }
    }
}
