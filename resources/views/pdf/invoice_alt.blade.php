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

        .description { padding-bottom: 10mm;}

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
@endphp
<body>
    <table>
        {{-- Intestazione --}}
        @php
            $sedeLegale = $invoice->company->address . ' - ' .
                        $invoice->company->city->zip_code . ' ' .
                        $invoice->company->city->name .
                        ' (' . $invoice->company->city->province->code . ')';
            $contatti = 'Tel. ' .  $invoice->company->phone . ' - Fax ' . $invoice->company->fax;
            $cf = $invoice->company->register . $invoice->company->registerProvince?->name . ' - CF ' . $invoice->company->tax_number . ' - P.I. ' . $invoice->company->vat_number;
            $rea = 'R.E.A. ' . $invoice->company->rea_number . ' - Cap. Soc. I.V. Euro' . $invoice->company->nominal_capital;
        @endphp
        <tr>
            <td rowspan="5" style="width: 20%; vertical-align: top; text-align: center;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}"
                        style="max-width: 100%; height: auto;"
                        alt="Logo">
                @else
                    <div>Logo non disponibile</div>
                @endif
            </td>
            <td colspan="4" class='bold left padding_company'>{{ $invoice->company->name }}</td>
        </tr>
        <tr>
            <td colspan="4" class='left padding_company'>Sede Legale: {{ $sedeLegale }}</td>
        </tr>
        <tr><td colspan="4" class='left padding_company'>{{ $contatti }}</td></tr>
        <tr><td colspan="4" class='left padding_company'>{{ $cf }}</td></tr>
        <tr><td colspan="4" class='left padding_company'>{{ $rea }}</td></tr>
        <tr>
            <td style="padding-top: 5mm; padding-bottom: 5mm;" colspan="5"></td>
        </tr>
    </table>
    <table>
        {{-- Destinatario --}}
        @php
            $cliente = "";
            switch($invoice->client->subtype->value){
                case "company":
                case "man":
                case "woman":
                case "professional":
                    $cliente .= "Spett.le ";
                case "city":
                    $cliente .= "Spett.le Amministrazione Comunale di ";
                    break;
                case "union":
                    $cliente .= "Spett.le Unione di comuni ";
                    break;
                case "federation":
                    $cliente .= "Spett.le Federazione di comuni ";
                    break;
                case "province":
                    $cliente .= "Spett.le amministrazione provinciale di ";
                    break;
            }
            $cliente .= $invoice->client->denomination;
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
        <tr>
            <td class="right" style="width: 55%;"></td>
            <td style="width: 45%">P.I. {{ $invoice->client->vat_code }}</td>
        </tr>
        <tr>
            <td class="right" style="width: 55%;"></td>
            <td style="width: 45%">C.F. {{ $invoice->client->tax_code }}</td>
        </tr>
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
            <td colspan="5" class='description'>{{ $invoice->description }}</td>
        </tr>
        {{-- Voci fattura inserite dall'operatore --}}
        @foreach($invoice->invoiceItems as $item)
            @php
                // dd($invoice->invoiceItems);
            @endphp
            @if($item->invoice_element_id)
                <tr>
                    <td style="width: 5%"></td>
                    <td style="width: 60%">{{ $item->description }}</td>
                    <td style="width: 15%">{{ $invoice->currency ?? 'Euro' }}</td>
                    <td style="width: 15%" class="right">{{ number_format($item->total, 2, ',', '.') }}</td>
                    <td style="width: 5%"></td>
                </tr>
            @endif
        @endforeach
        <tr>
            <td style="width: 5%"></td>
            <td style="width: 60%;"></td>
            <td colspan="2" style="width: 30%;" class="dashed_bottom">
                &nbsp;
            </td>
        </tr>
        {{-- Riepiloghi IVA --}}
        @foreach($vats as $vat)
            @php
                // dd($vats);
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
            <tr>
                <td style="width: 5%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}"></td>
                <td style="width: 60%; {{ $loop->first ? 'padding-top: 5mm;' : '' }}">Totale imponibile {{ $labelImponibile }}</td>
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
        @endforeach
        <tr>
            <td style="width: 5%"></td>
            <td style="width: 60%;"></td>
            <td colspan="2" style="width: 30%;" class="dashed_bottom">
                &nbsp;
            </td>
        </tr>
        {{-- Rimborso spese notifica --}}
        @php
            $hasRimborso = false;
        @endphp
        @foreach($invoice->invoiceItems as $item)
            @if(str_contains($item->description, 'Rimborso spese di notifica da '))
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
            @endif
        @endforeach
        {{-- Se nessun rimborso trovato, stampa riga con 0,00 --}}
        @if(! $hasRimborso)
            <tr>
                <td style="width: 5%; padding-top: 5mm;"></td>
                <td style="width: 60%; padding-top: 5mm;">Rimborso spese di notifica</td>
                <td style="width: 15%; padding-top: 5mm;">Euro</td>
                <td style="width: 15%; padding-top: 5mm; text-align: right;">0,00</td>
                <td style="width: 5%; padding-top: 5mm;"></td>
            </tr>
        @endif
        <tr>
            <td style="width: 5%"></td>
            <td style="width: 60%"></td>
            <td colspan="2" style="width: 30%;" class="dashed_bottom">&nbsp;</td>
            <td style="width: 5%"></td>
        </tr>
        {{-- Imposta di bollo --}}
        @php
            $stamp = false;
        @endphp
        @foreach($invoice->invoiceItems as $item)
            @if(!$item->invoice_element_id && (! (int) $item->auto))
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
        {-- Totale --}
        <tr>
            <td style="width: 5%; padding-top: 5mm;"></td>
            <td style="width: 60%; padding-top: 5mm;">TOTALE</td>
            <td style="width: 15%; padding-top: 5mm;">{{ $invoice->currency ?? 'Euro' }}</td>
            <td style="width: 15%; padding-top: 5mm; text-align: right;">
                {{ number_format($invoice->total, 2, ',', '.') }}
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
        // dd($invoice->company->stampDuty->virtual_stamp && $stamp);
            use App\Enums\ClientType;
            $split = $invoice->client->type == ClientType::PUBLIC;
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
            <td colspan="5" class="">Documento privo di valenza fiscale ai sensi dell'art. 21 Dpr 633/72. L'originale è disponibile all'indirizzo telematico da Lei fornito oppure nella Sua area riservata dell'Agenzia delle Entrate.</td>
        </tr>
        <tr><td colspan="5" class=""></td></tr>
        <tr>
            <td style="padding-top: 5mm;" colspan="5" class="right">CIG: {{$invoice->contract->cig_code}} - Codice Unico Ufficio: {{$invoice->contract->office_code}} - A.B. {{$invoice->budget_year}}</td>
        </tr>
    </table>
</body>
