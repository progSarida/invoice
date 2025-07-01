<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Fattura {{ $invoice->getInvoiceNumber() }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 2.75mm; }
        table { width: 100%; border-collapse: collapse; border: 0.2px solid #000; }
        .border { border: 0.2px solid #000; }
        .border_left   { border-left:   0.2px solid #000; }
        .border_top    { border-top:    0.2px solid #000; }
        .border_right  { border-right:  0.2px solid #000; }
        .border_bottom { border-bottom: 0.2px solid #000; }
        .no-border { border: none; }
        .no_border_left   { border-left:   none; }
        .no_border_top    { border-top:    none; }
        .no_border_right  { border-right:  none; }
        .no_border_bottom { border-bottom: none; }
        .bold { font-weight: bold; }
        .left { text-align: left; }
        .center { text-align: center; }
        .right { text-align: right; }
        .padding { padding-top: 1mm; padding-bottom: 1mm;}

        .dati_sdi { margin-bottom: 10px; }
        .header { margin-bottom: 46px; }
        .causal { margin-bottom: 21px; }
        .items { margin-bottom: 21px; }
        .vat { margin-bottom: 21px; }
        .total { margin-bottom: 21px; }
    </style>
</head>
<body>
    {{-- Dati SDI --}}
    <div class="dati_sdi">
        Data trasmissione: {{ $invoice->lastSdiNotification?->date ?? 'N/A' }} - Identificativo SDI: {{ $invoice->lastSdiNotification?->code ?? 'N/A' }}
    </div>
    {{-- Dati attori --}}
    <table class="header left">
        <tr>
            <td colspan="50" class="bold">Cedente/prestatore (fornitore)</td>
            <td colspan="50" class="bold">Cessionario/committente (cliente)</td>
        </tr>
        <tr>
            <td colspan="1"></td>
            <td colspan="49">Identificativo fiscale ai fini IVA: <b>IT{{ $invoice->company->tax_number }}</b></td>
            <td colspan="1"></td>
            <td colspan="49">Identificativo fiscale ai fini IVA: <b>IT{{ $invoice->client->tax_code }}</b></td>
        </tr>
        <tr>
            <td colspan="1"></td>
            <td colspan="49">Codice Fiscale: <b>{{ $invoice->company->tax_number }}</b></td>
            <td colspan="1"></td>
            <td colspan="49">Codice Fiscale: <b>{{ $invoice->client->tax_code }}</b></td>
        </tr>
        <tr>
            <td colspan="1"></td>
            <td colspan="49">Denominazione: <b>{{ $invoice->company->name }}</b></td>
            <td colspan="1"></td>
            <td colspan="49">Denominazione: <b>{{ $invoice->client->denomination }}</b></td>
        </tr>
        <tr>
            <td colspan="1"></td>
            <td colspan="49">Regime fiscale: <b>{{ $invoice->company?->fiscalProfile?->tax_regime?->getCode() }} ({{ $invoice->company?->fiscalProfile?->tax_regime?->getDescription() }})</b></td>
            <td colspan="1"></td>
            <td colspan="49">Indirizzo: <b>{{ $invoice->client->address }}</b></td>
        </tr>
        <tr>
            <td colspan="1"></td>
            <td colspan="49">Indirizzo: <b>{{ $invoice->company->address }}</b></td>
            <td colspan="1"></td>
            <td colspan="49">Comune: <b>{{ $invoice->client->city->name }}</b> Provincia: <b>{{ $invoice->client->city->province->code }}</b></td>
        </tr>
        <tr>
            <td colspan="1"></td>
            <td colspan="49">Comune: <b>{{ $invoice->company->city->name }}</b> Provincia: <b>{{ $invoice->company->city->province->code }}</b></td>
            <td colspan="1"></td>
            <td colspan="49">Cap: <b>{{ $invoice->client->city->zip_code }}</b> Nazione: <b>IT</b></td>
        </tr>
        <tr>
            <td colspan="1"></td>
            <td colspan="49">Cap: <b>{{ $invoice->company->city->zip_code }}</b> Nazione: <b>IT</b></td>
            <td colspan="1"></td>
            <td colspan="49"></td>
        </tr>
        <tr class="border_bottom border_top">
            <td colspan="25" class="center border_right padding">Tipologia documento</td>
            <td colspan="8" class="center border_right padding">Art. 73</td>
            <td colspan="24" class="center border_right padding">Numero documento</td>
            <td colspan="20" class="center border_right padding">Data documento</td>
            <td colspan="23" class="center padding">Codice destinatario</td>
        </tr>
        <tr class="bold">
            <td colspan="25" class="center">{{ $invoice->docType->name }} ({{ strtolower($invoice->docType->description) }})</td>
            <td colspan="8" class="center"></td>
            <td colspan="24" class="right">{{ $invoice->getNewInvoiceNumber() }}</td>
            <td colspan="20" class="center">{{ $invoice->invoice_date }}</td>
            <td colspan="23" class="center">{{ $invoice->contract->office_code }}</td>
        </tr>
    </table>
    {{--  --}}
    <table class="causal">
        <tr class="border_bottom">
            <td class="center padding">Causale</td>
        </tr>
        <tr>
            <td class="padding">Descrizione</td>
        </tr>
    </table>
    {{--  --}}
    <table class="items">
        <tr class="center border_bottom">
            <td style="width: 10%" class="border_right padding">Cod. articolo</td>
            <td style="width: 35%" class="border_right padding">Descrizione</td>
            <td style="width: 10%" class="border_right padding">Quantità</td>
            <td style="width: 12%" class="border_right padding">Prezzo unitario</td>
            <td style="width: 5%" class="border_right padding">UM</td>
            <td style="width: 12%" class="border_right padding">Sconto o magg.</td>
            <td style="width: 6%" class="border_right padding">%IVA</td>
            <td style="width: 10%" class="padding">Prezzo totale</td>
        </tr>
        <tr>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 38%" class="padding"> Contratto {{ $invoice->contractDetail->number }} del {{ $invoice->contractDetail->date->format('d-m-Y') }}, CIG: {{ $invoice->contract->cig_code }}</td>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 5%" class="padding"></td>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 7%" class="padding"></td>
            <td style="width: 10%" class="padding"></td>
        </tr>
        <tr>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 38%" class="padding"> --------------- </td>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 5%" class="padding"></td>
            <td style="width: 10%" class="padding"></td>
            <td style="width: 7%" class="padding"></td>
            <td style="width: 10%" class="padding"></td>
        </tr>
        @foreach ($invoice->invoiceItems as $item)
            <tr>
                <td style="width: 10%" class="padding"></td>
                <td style="width: 38%" class="padding">{{ $item->description }}</td>
                <td style="width: 10%" class="padding"></td>
                <td style="width: 10%" class="padding right">{{ number_format((float) $item->amount, 2, ',', '.') }}</td>
                <td style="width: 5%" class="padding"></td>
                <td style="width: 10%" class="padding"></td>
                <td style="width: 7%" class="padding right">
                    {{ $item->vat_code_type->getRate() == '0'
                        ? $item->vat_code_type->getCode()
                        : number_format((float) $item->vat_code_type->getRate(), 2, ',', '.') }}
                </td>
                <td style="width: 10%" class="padding right">{{ number_format((float) $item->amount, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>
    {{--  --}}
    <table class="vat">
        <tr class="center bold border_bottom">
            <td colspan="6" class="padding">RIEPILOGHI IVA</td>
        </tr>
        <tr class="center border_bottom">
            <td style="width: 37%" class="border_right bold padding">esigibilità iva / riferimenti normativi</td>
            <td style="width: 9%" class="border_right padding">%IVA</td>
            <td style="width: 18%" class="border_right padding">Spese accessorie</td>
            <td style="width: 7%" class="border_right padding">Arr.</td>
            <td style="width: 17%" class="border_right padding">Totale imponibile</td>
            <td style="width: 12%" class="border_right padding">Totale imposta</td>
        </tr>
        @foreach ($vats as $vat)
            <tr>
                <td style="width: 37%" class="padding">{{ $vat['norm'] }}</td>
                <td style="width: 9%" class="right padding"> {{ $vat['%'] }} </td>
                <td style="width: 18%" class="padding"></td>
                <td style="width: 7%" class="padding"></td>
                <td style="width: 17%" class="right padding">{{ number_format((float) $vat['taxable'], 2, ',', '.') }}</td>
                <td style="width: 12%" class="right padding">{{ number_format((float) $vat['vat'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>
    {{--  --}}
    <table class="total">
        <tr class="center bold border_bottom">
            <td colspan="5" class="padding">TOTALI</td>
        </tr>
        <tr class="center border_bottom">
            <td style="width: 19%" class="border_right padding">Importo bollo</td>
            <td style="width: 19%" class="border_right padding">Bollo virtuale</td>
            <td style="width: 32%" class="border_right padding">Sconto/Maggiorazione</td>
            <td style="width: 5%" class="border_right padding">Arr.</td>
            <td style="width: 25%" class="border_right padding">Totale documento</td>
        </tr>
        <tr>
            <td style="width: 19%" class="right padding">{{ $invoice->company->stampDuty->active ? number_format((float) $invoice->company->stampDuty->amount, 2, ',', '.')  : ''}}</td>
            <td style="width: 19%" class="center padding">{{ $invoice->company->stampDuty->active ? 'SI' : ''}}</td>
            <td style="width: 32%" class="padding"></td>
            <td style="width: 5%" class="padding"></td>
            <td style="width: 25%" class="right padding bold">{{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
        </tr>
    </table>

    <table>
        <tr class="center border_bottom">
            <td style="width: 29%" class="border_right padding">Modalità pagamento</td>
            <td style="width: 25%" class="border_right padding">Coordinate bancarie</td>
            <td style="width: 25%" class="border_right padding">Istituto</td>
            <td style="width: 11%" class="border_right padding">Data scadenza</td>
            <td style="width: 10%" class="border_right padding">Importo</td>
        </tr>
        <tr>
            <td style="width: 29%" class="right padding">{{ $invoice->bankAccount?->payment_type?->getLabel() }}</td>
            <td style="width: 25%" class="center padding">{{ $invoice->bankAccount?->iban ? 'IBAN ' . $invoice->bankAccount->iban : '' }}</td>
            <td style="width: 25%" class="padding">{{ $invoice->bankAccount?->name }}</td>
            <td style="width: 11%" class="center padding">{{ $invoice->invoice_date->addDays($invoice->payment_days)->format('d/m/Y') }}</td>
            <td style="width: 10%" class="right padding">{{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
        </tr>
    </table>


    {{-- Linee Fattura --}}
    {{-- <table>
        <tr>
            <th>Descrizione</th>
            <th class="right">Quantità</th>
            <th class="right">Prezzo Unitario</th>
            <th class="right">IVA %</th>
            <th class="right">Totale</th>
        </tr>
        @foreach ($invoice->invoiceItems as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td class="right">{{ number_format((float) $item->qty, 2, ',', '.') }}</td>
            <td class="right">€ {{ number_format((float) $item->price, 2, ',', '.') }}</td>
            <td class="right">{{ (float) $item->vat }}%</td>
            <td class="right">€ {{ number_format((float) $item->total, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </table> --}}

    {{-- Riepilogo IVA --}}
    {{-- <table>
        <tr>
            <th>Aliquota</th>
            <th class="right">Imponibile</th>
            <th class="right">Imposta</th>
        </tr>
        @php
            $vatSummary = $invoice->invoiceItems->groupBy('vat');
        @endphp
        @foreach ($vatSummary as $vat => $items)
        @php
            $vatRate = (float) $vat;
            $imponibile = $items->sum(function($item) {
                return (float) $item->qty * (float) $item->price;
            });
            $iva = $imponibile * ($vatRate / 100);
        @endphp
        <tr>
            <td>{{ $vatRate }}%</td>
            <td class="right">€ {{ number_format($imponibile, 2, ',', '.') }}</td>
            <td class="right">€ {{ number_format($iva, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </table> --}}

    {{-- Totali --}}
    {{-- <table>
        @php
            $totalImponibile = $invoice->invoiceItems->sum(fn($i) => (float) $i->qty * (float) $i->price);
            $totalIVA = $invoice->invoiceItems->sum(fn($i) => (float) $i->qty * (float) $i->price * ((float) $i->vat / 100));
        @endphp
        <tr>
            <td class="right bold">Totale Imponibile</td>
            <td class="right">€ {{ number_format($totalImponibile, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="right bold">Totale IVA</td>
            <td class="right">€ {{ number_format($totalIVA, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="right bold">Totale Documento</td>
            <td class="right">€ {{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
        </tr>
    </table> --}}

    {{-- Pagamento --}}
    {{-- <table>
        <tr><th colspan="2">Pagamento</th></tr>
        <tr>
            <td><strong>Modalità</strong></td>
            <td>{{ $invoice->payment_type->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Giorni</strong></td>
            <td>{{ $invoice->payment_days ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>IBAN</strong></td>
            <td>{{ $invoice->bankAccount->iban ?? 'N/A' }}</td>
        </tr>
    </table> --}}

    {{-- Note --}}
    {{-- @if ($invoice->free_description)
    <table>
        <tr><th>Note</th></tr>
        <tr><td>{{ $invoice->free_description }}</td></tr>
    </table>
    @endif --}}

    {{-- Footer --}}
    {{-- <div class="footer">
        <p>Documento emesso in formato elettronico conforme D.M. 55/2013</p>
    </div> --}}

</body>
</html>
