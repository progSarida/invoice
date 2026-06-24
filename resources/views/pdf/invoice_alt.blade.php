<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Fattura {{ $invoice->getInvoiceNumber() }}</title>
    <style>
        /*body { font-family: Helvetica, Arial, sans-serif; font-size: 3.25mm; margin-top: 60mm; }*/
        body { font-family: Helvetica, Arial, sans-serif; font-size: 3.25mm; }
        table { width: 100%; border-collapse: collapse; }

        /* td { border: 0.2px solid #000; } */

        .border { border: 0.2px solid #000; }
        .border_left   { border-left:   0.2px solid #000; }
        .border_top    { border-top:    0.2px solid #000; }
        .border_right  { border-right:  0.2px solid #000; }
        .border_bottom { border-bottom: 0.2px solid #000; }

        .bold { font-weight: bold; }
        .left { text-align: left; }
        .center { text-align: center; }
        .right { text-align: right; }
        .padding { padding-top: 1mm; padding-bottom: 1mm;}
        .padding_company { padding-left: 5mm;}

        .company_name { font-size: 5mm; }

        .description { padding-bottom: 1mm;}
        .free_description { padding-bottom: 10mm;}

        .note { font-style: italic; padding-top: 3mm;}

        .dashed_bottom { border-bottom: 0.5px dashed #000;}

        .dati_sdi { margin-bottom: 10px; }
        /* .header { margin-bottom: 46px; } */
        .causal { margin-bottom: 21px; }
        .items { margin-bottom: 21px; }
        .vat { margin-bottom: 21px; }
        .total { margin-bottom: 21px; }

        thead { display: table-header-group; /* Ripete l'intestazione ad ogni pagina */ }
        tfoot { display: table-footer-group; }
    </style>
</head>
@php
    $doc = strtoupper($invoice->docType->description)
             . ' N. '
             . $invoice->getNewInvoiceNumber()
             . ' del '
             . $invoice->invoice_date->format('d-m-Y');

    $number = $invoice->company->vat_number ?? $invoice->company->tax_number;
    $logoPath = storage_path('app/public/logos/logo_'. $number . '.png');
    $logoSrc = null;
// dd($vats);
    if (file_exists($logoPath)) {
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $logoSrc = 'data:image/png;base64,' . $logoBase64;
    }

    use App\Enums\ClientType;
    use App\Models\BankAccount;
    $notRound = BankAccount::find($invoice->bank_account_id)?->name != 'Giroconto';
    $split = $invoice->client?->type == ClientType::PUBLIC && $notRound;

    $fullTotal = 0;
    $vattedTotal = 0;
    $noVattedTotal = 0;
@endphp
<body>
    <table>
        {{-- Intestazione --}}
        @php
            $sedeLegale1 = $invoice->company->address;
            $sedeLegale2 = $invoice->company->city->zip_code . ' ' .
                        $invoice->company->city->name .
                        ' (' . $invoice->company->city->province->code . ')';

            $contatti1 = '';
            if($invoice->company->phone) $contatti1 .= 'Tel. ' .  $invoice->company->phone;
            if($invoice->company->phone && $invoice->company->fax) $contatti1 .= ' - ';
            if($invoice->company->fax) $contatti1 .= 'Fax: ' . $invoice->company->fax;

            $contatti2 = '';
            if($invoice->company->email) $contatti2 .= 'Email: ' .  $invoice->company->email;

            $contatti3 = '';
            if($invoice->company->pec) $contatti3 .= 'Pec: ' . $invoice->company->pec;

            $cf1 = '';
            if($invoice->company->register) $cf1 .= $invoice->company->register;
            if($invoice->company->register && $invoice->company->registerProvince) $cf1 .= ' ';
            if($invoice->company->registerProvince) $cf1 .= $invoice->company->registerProvince?->name;
            // if($invoice->company->phregisterone || $invoice->company->registerProvince) $cf1 .= ' - ';

            $cf2 = '';
            if($invoice->company->tax_number) $cf2 .= 'CF ' . $invoice->company->tax_number; else $cf2 .= '';
            if($invoice->company->tax_number && $invoice->company->vat_number) $cf2 .= ' - ';
            if($invoice->company->vat_number) $cf2 .= 'P.I. ' . $invoice->company->vat_number; else $cf2 .= '';

            $rea = '';
            if($invoice->company->rea_number) $rea .= 'R.E.A. ' . $invoice->company->rea_number;
            if($invoice->company->rea_number && $invoice->company->nominal_capital) $rea .= ' - ';
            if($invoice->company->rea_number) $rea .= 'Cap. Soc. I.V. Euro' . $invoice->company->nominal_capital;
            $voice = false;
        @endphp
        {{-- Con logo --}}
        @if($logoSrc)
        <tr>
            <td rowspan="5" style="width: 20%; vertical-align: top; text-align: center;">
                <img src="{{ $logoSrc }}"
                    style="max-width: 100%; height: auto;"
                    alt="Logo">
            </td>
            <td colspan="4" class='bold left padding_company'>{{ $invoice->company->name }}</td>
        </tr>
        <tr>
            <td colspan="4" class='left padding_company'>Sede Legale: {{ $sedeLegale }}</td>
        </tr>
        @if($contatti1 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $contatti1 }}</td></tr>
        @endif
        @if($contatti2 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $contatti2 }}</td></tr>
        @endif
        @if($contatti3 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $contatti3 }}</td></tr>
        @endif
        @if($cf !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $cf }}</td></tr>
        @endif
        @if($rea !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $rea }}</td></tr>
        @endif
        <tr>
            <td style="padding-top: 5mm; padding-bottom: 5mm;" colspan="5"></td>
        </tr>
        @else
        {{-- Senza logo --}}
        <tr>
            <td colspan="4" class='bold left padding_company company_name'>{{ $invoice->company->name }}</td>
        </tr>
        <tr>
            <td colspan="4" class='left padding_company'>Sede Legale: {{ $sedeLegale1 }}</td>
        </tr>
        <tr>
            <td colspan="4" class='left padding_company'>{{ $sedeLegale2 }}</td>
        </tr>
        @if($contatti1 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $contatti1 }}</td></tr>
        @endif
        @if($contatti2 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $contatti2 }}</td></tr>
        @endif
        @if($contatti3 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $contatti3 }}</td></tr>
        @endif
        @if($cf1 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $cf1 }}</td></tr>
        @endif
        @if($cf2 !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $cf2 }}</td></tr>
        @endif
        @if($rea !== '')
        <tr><td colspan="4" class='left padding_company'>{{ $rea }}</td></tr>
        @endif
        <tr>
            <td style="padding-top: 5mm; padding-bottom: 5mm;" colspan="4"></td>
        </tr>
        @endif
    </table>
    <table>
        {{-- Destinatario --}}
        @php
            $cliente = $invoice->client->denomination;
            $indirizzoCliente = $invoice->client->city->zip_code . ' ' .
                        $invoice->client->city->name . ' ' .
                        $invoice->client->city->province->code;
        @endphp
        <tr>
            <td class="right" style="padding-right: 2mm; width: 55%;">Spett.le</td>
            <td style="width: 45%">{{ $cliente }}</td>
        </tr>
        <tr>
            <td class="right" style="width: 55%;"></td>
            <td style="width: 45%">{{ $invoice->client->address }}</td>
        </tr>
        <tr>
            <td class="right" style="width: 55%;"></td>
            <td style="width: 45%">{{ $indirizzoCliente }}</td>
        </tr>
        @if($invoice->client->vat_code)
        <tr>
            <td class="right" style="width: 55%;"></td>
            <td style="width: 45%">P.I. {{ $invoice->client->vat_code }}</td>
        </tr>
        @endif
        @if($invoice->client->tax_code)
        <tr>
            <td class="right" style="width: 55%;"></td>
            <td style="width: 45%">C.F. {{ $invoice->client->tax_code }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding-top: 5mm; padding-bottom: 5mm;" colspan="5"></td>
        </tr>
    </table>
    <table>
        {{-- Dati fattura --}}
        <tr>
            <td colspan="5" class='bold'>{{ $doc }}</td>
        </tr>
        <tr>
            {{-- <td colspan="5" class='description'>({{ substr($invoice->budget_year, -2) }}) {{ $invoice->description }}</td> --}}
            <td colspan="5" class='description'>{{ $invoice->description }}</td>
        </tr>
        <tr>
            <td colspan="5" class='free_description'>{{ $invoice->free_description }}</td>
        </tr>
        {{-- Voci fattura inserite dall'operatore --}}
        @foreach($invoice->invoiceItems as $item)
            @php
                // dd($invoice->invoiceItems);
                $fullTotal += $item->total;
                $noVattedTotal += $item->amount;
            @endphp
            @if(!str_contains($item->description, 'Rimborsi') &&                                    // la descrizione non contiene la scritta 'Rimborsi' e
                (
                    ($item->invoice_element_id && !$item->auto) ||                                      // è una voce inserira dall'operatore oppure
                        ($item->invoice_id <= 6338 &&                                                       // è una fattura importata dal vecchio programma e
                            !$item->auto &&                                                                     // non è una voce inserita automaticamente e
                            (!str_contains($item->description, '633/72') &&                                     // non fa riferimento al DPR 633/72
                                !str_contains($item->description, 'esente')                                     // non è indicata come esente iva
                            )
                        )
                )
            )
                @php
                    $voice = true;
                @endphp
                <tr>
                    <td style="width: 5%"></td>
                    <td style="width: 60%">{{ $item->description }}</td>
                    <td style="width: 15%">{{ $invoice->currency ?? 'Euro' }}</td>
                    <td style="width: 15%" class="right">{{ number_format($item->amount, 2, ',', '.') }}</td>
                    <td style="width: 5%"></td>
                </tr>
            @endif
        @endforeach
        @if($voice)
        <tr>
            <td style="width: 5%"></td>
            <td style="width: 60%;"></td>
            <td colspan="2" style="width: 30%;" class="dashed_bottom">
                &nbsp;
            </td>
        </tr>
        @endif
        {{-- Riepiloghi IVA --}}
        @php
            $voiceVat = false;
        @endphp
        @foreach($vats as $vat)
            @php
                // dd($vats);
                $free = $vat['free'];
                $labelImponibile = (is_numeric($vat['%']) && $vat['%'])
                    ? 'I.V.A. ' . number_format($vat['%'], 2, ',', '.') . '%'
                    : ($vat['%'] ? 'I.V.A. ' . $vat['%'] : '');
                $labelIVA = (is_numeric($vat['%']) && $vat['%'])
                    ? 'I.V.A. ' . number_format($vat['%'], 2, ',', '.') . '%'
                    : ($vat['%'] ? 'I.V.A. ' . $vat['%'] : '');
                $vatAmount = (is_numeric($vat['%']) && $vat['%'])
                    ? $vat['taxable'] * ($vat['%'] / 100)
                    : $vat['vat'];
            @endphp
            @if(!$free)
            @php
                if($split) {
                    // $vattedTotal += $vatAmount;
                    // $fullTotal += $vatAmount;
                }
                $voiceVat = true;
            @endphp
            <tr>
                <td style="width: 5%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}"></td>
                {{-- <td style="width: 60%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}">Totale imponibile {{ $labelImponibile }}</td> --}}
                <td style="width: 60%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}">Totale imponibile</td>
                <td style="width: 15%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}">{{ $invoice->currency ?? 'Euro' }}</td>
                <td style="width: 15%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}" class="right">{{ number_format($vat['taxable'], 2, ',', '.') }}</td>
                <td style="width: 5%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}"></td>
            </tr>
            <tr>
                <td style="width: 5%"></td>
                <td style="width: 60%">{{ $labelIVA }}</td>
                <td style="width: 15%">{{ $invoice->currency ?? 'Euro' }}</td>
                <td style="width: 15%" class="right">{{ number_format($vatAmount, 2, ',', '.') }}</td>
                <td style="width: 5%"></td>
            </tr>
            @endif
        @endforeach
        @if($voiceVat)
        <tr>
            <td style="width: 5%"></td>
            <td style="width: 60%;"></td>
            <td colspan="2" style="width: 30%;" class="dashed_bottom">
                &nbsp;
            </td>
        </tr>
        @endif
        {{-- Rimborso spese notifica --}}
        @php
            $hasRimborso = false;
        @endphp
        @foreach($invoice->invoiceItems as $item)
            @if(str_contains($item->description, 'Rimborsi'))
                @php
                    $hasRimborso = true;
                @endphp

                <tr>
                    <td style="width: 5%; padding-top: 5mm;"></td>
                    <td style="width: 60%; padding-top: 5mm;">{{ $item->description }}</td>
                    <td style="width: 15%; padding-top: 5mm;">{{ $invoice->currency ?? 'Euro' }}</td>
                    <td style="width: 15%; padding-top: 5mm; text-align: right;">
                        {{ number_format($item->total, 2, ',', '.') }}
                    </td>
                    <td style="width: 5%; padding-top: 5mm;"></td>
                </tr>
                <tr>
                    <td style="width: 5%"></td>
                    <td style="width: 60%"></td>
                    <td colspan="2" style="width: 30%;" class="dashed_bottom">&nbsp;</td>
                    <td style="width: 5%"></td>
                </tr>
            @endif
        @endforeach
        {{-- Se nessun rimborso trovato, stampa riga con 0,00 --}}
        {{-- @if(! $hasRimborso)
            <tr>
                <td style="width: 5%; padding-top: 5mm;"></td>
                <td style="width: 60%; padding-top: 5mm;">Rimborso spese di notifica</td>
                <td style="width: 15%; padding-top: 5mm;">Euro</td>
                <td style="width: 15%; padding-top: 5mm; text-align: right;">0,00</td>
                <td style="width: 5%; padding-top: 5mm;"></td>
            </tr>
            <tr>
                <td style="width: 5%"></td>
                <td style="width: 60%"></td>
                <td colspan="2" style="width: 30%;" class="dashed_bottom">&nbsp;</td>
                <td style="width: 5%"></td>
            </tr>
        @endif --}}
        {{-- Imposta di bollo --}}
        @php
            $stamp = false;
        @endphp
        @foreach($invoice->invoiceItems as $item)
            {{-- @if(!$item->invoice_element_id && (! (int) $item->auto) && !str_contains($item->description, 'Rimborsi escl.Art. 15 ex D.P.R. 633/72')) --}}
            @if(!$item->invoice_element_id && (! (int) $item->auto) && !str_contains($item->description, 'Rimborsi')  && ($item->invoice_id > 6338 ||
                $item->invoice_id <= 6338 && (str_contains($item->description, '633/72') || str_contains($item->description, 'esente'))))
            @php
                $stamp = true;
            @endphp
                <tr>
                    <td style="width: 5%; padding-top: 5mm;"></td>
                    <td style="width: 60%; padding-top: 5mm;">{{ $item->description }}</td>
                    <td style="width: 15%; padding-top: 5mm;">{{ $invoice->currency ?? 'Euro' }}</td>
                    <td style="width: 15%; padding-top: 5mm; text-align: right;">
                        {{ number_format($item->total, 2, ',', '.') }}
                    </td>
                    <td style="width: 5%; padding-top: 5mm;"></td>
                </tr>
                <tr>
                    <td style="width: 5%"></td>
                    <td style="width: 60%;"></td>
                    <td colspan="2" style="width: 30%;" class="dashed_bottom">
                        &nbsp;
                    </td>
                </tr>
            @endif
        @endforeach
        {-- Totale ivato --}
        @php
        // dd($fullTotal, $vattedTotal, $noVattedTotal, );
        @endphp
        <tr>
            <td style="width: 5%; padding-top: 5mm;"></td>
            <td style="width: 60%; padding-top: 5mm;">TOTALE</td>
            <td style="width: 15%; padding-top: 5mm;">{{ $invoice->currency ?? 'Euro' }}</td>
            <td style="width: 15%; padding-top: 5mm; text-align: right;">
                {{ number_format(($invoice->total), 2, ',', '.') }}
            </td>
            <td style="width: 5%; padding-top: 5mm;"></td>
        </tr>
        <tr>
            <td style="width: 5%"></td>
            <td style="width: 60%"></td>
            <td colspan="2" style="width: 30%;" class="dashed_bottom">&nbsp;</td>
            <td style="width: 5%"></td>
        </tr>
        @php
            $split = false;
            if($invoice->client?->type == \App\Enums\ClientType::PUBLIC && $notRound){
                $totalPay = $invoice->no_vat_total;
                $split = true;
            }
            else{
                $totalPay = $invoice->total;
            }
        @endphp
        {{-- @if($fullTotal != $noVattedTotal) --}}
        @if($split)
        {-- Totale a doversi --}
        <tr>
            <td style="width: 5%; padding-top: 5mm;"></td>
            <td style="width: 60%; padding-top: 5mm;">TOTALE A DOVERSI</td>
            <td style="width: 15%; padding-top: 5mm;">{{ $invoice->currency ?? 'Euro' }}</td>
            <td style="width: 15%; padding-top: 5mm; text-align: right;">
                {{ number_format($totalPay, 2, ',', '.') }}
            </td>
            <td style="width: 5%; padding-top: 5mm;"></td>
        </tr>
        <tr>
            <td style="width: 5%"></td>
            <td style="width: 60%"></td>
            <td colspan="2" style="width: 30%;" class="dashed_bottom">&nbsp;</td>
            <td style="width: 5%"></td>
        </tr>
        @endif
        @php
        // dd($invoice->company->stampDuty->virtual_stamp && $stamp);
            // use App\Enums\ClientType;
            // $split = $invoice->client?->type == ClientType::PUBLIC;
        @endphp
        <tr>
            <td colspan="5" class="note">{{ $split ? 'Iva da versare a cura del concessionario o committente ai sensi dell\'art. 17 - ter del D.P.N.R. Nr 633/1972' : ''}}</td>
        </tr>
        <tr>
            <td colspan="5" class="note">{{ ($invoice->company->stampDuty->virtual_stamp && $stamp) ? 'Imposta di bollo assolta in modo virtuale' : ''}}</td>
        </tr>
        <tr>
            <td style="padding-top: 2mm; padding-bottom: 2mm;" colspan="5" class="dashed_bottom"></td>
        </tr>
        <tr>
            <td colspan="5" style="padding-top: 4mm;">Banca appoggio: {{ $invoice->bankAccount->name }}</td>
        </tr>
        @if($invoice->bankAccount->agency)
            <tr>
                <td colspan="5" style="padding-top: 1mm;">Agenzia di: {{ $invoice->bankAccount->agency }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="5" style="padding-top: 1mm;">IBAN: {{ $invoice->bankAccount->iban }}</td>
        </tr>
        <tr>
            <td style="padding-top: 2mm; padding-bottom: 2mm;" colspan="5" class="dashed_bottom"></td>
        </tr>
        <tr>
            <td colspan="5" class="">Documento privo di valenza fiscale ai sensi dell'art. 21 DPR 633/72. L'originale è disponibile all'indirizzo telematico da Lei fornito oppure nella Sua area riservata dell'Agenzia delle Entrate.</td>
        </tr>
        <tr><td colspan="5" class=""></td></tr>
        <tr>
            @php
                $cig_code = '';
                $office_code = '';
                if($invoice->contract?->cig_code && strpos($invoice->contract?->cig_code, '#') === false) $cig_code = 'CIG: ' . $invoice->contract?->cig_code . ' - ';
                if($invoice->contract?->cig_code) $office_code = 'Codice Unico Ufficio: ' . $invoice->contract?->office_code . ' - ';
            @endphp
            <td style="padding-top: 5mm;" colspan="5" class="right">{{$cig_code}}{{$office_code}}A.B. {{$invoice->budget_year}}</td>
        </tr>
    </table>
</body>
