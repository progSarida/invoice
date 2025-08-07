<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Fattura {{ $invoice->getInvoiceNumber() }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 2.75mm; margin-top: 60mm; /* Spazio per il fixed-header */ }
        table { width: 100%; border-collapse: collapse; border: 0.2px solid #000; }

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
<body>
    {{-- Include l'header fisso che sarà ripetuto ad ogni pagina --}}
    @include('pdf.invoice-header')

    {{-- Causale --}}
    <table class="causal">
        <tr class="border_bottom">
            <td class="center padding">Causale</td>
        </tr>
        <tr>
            @php
                $paied = '';
                if (in_array($invoice->payment_status->value, ['paied', 'paied_credit_note'])) {
                    $paied = 'FATTURA PAGATA - ';
                }
            @endphp
            <td class="padding">{{ $paied }}{{ $invoice->description }}</td>
        </tr>
    </table>

    {{-- Voci fattura --}}
    <table class="items">
        <thead>
            <tr class="center border_bottom">
                <th style="width: 10%" class="border_right padding">Cod. articolo</th>
                <th style="width: 35%" class="border_right padding">Descrizione</th>
                <th style="width: 10%" class="border_right padding">Quantità</th>
                <th style="width: 12%" class="border_right padding">Prezzo unitario</th>
                <th style="width: 5%" class="border_right padding">UM</th>
                <th style="width: 12%" class="border_right padding">Sconto o magg.</th>
                <th style="width: 6%" class="border_right padding">%IVA</th>
                <th style="width: 10%" class="padding">Prezzo totale</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="padding"></td>
                <td class="padding">
                    Contratto {{ $invoice->contractDetail->number }} del {{ $invoice->contractDetail->date->format('d-m-Y') }}, CIG: {{ $invoice->contract->cig_code }}
                </td>
                <td class="padding"></td><td class="padding"></td><td class="padding"></td><td class="padding"></td><td class="padding"></td><td class="padding"></td>
            </tr>
            <tr>
                <td class="padding"></td>
                <td class="padding">---------------</td>
                <td class="padding"></td><td class="padding"></td><td class="padding"></td><td class="padding"></td><td class="padding"></td><td class="padding"></td>
            </tr>
            @php
                $items = $invoice->invoiceItems instanceof \Illuminate\Support\Collection
                    ? $invoice->invoiceItems->where('auto', false)
                    : $invoice->invoiceItems()->where('auto', false)->get();
            @endphp
            @foreach ($items as $item)
                <tr>
                    <td class="padding"></td>
                    <td class="padding">{{ $item->description }}</td>
                    <td class="padding"></td>
                    <td class="padding right">{{ number_format((float) $item->amount, 2, ',', '.') }}</td>
                    <td class="padding"></td>
                    <td class="padding"></td>
                    <td class="padding right">
                        {{ $item->vat_code_type->getRate() == '0'
                            ? $item->vat_code_type->getCode()
                            : number_format((float) $item->vat_code_type->getRate(), 2, ',', '.') }}
                    </td>
                    <td class="padding right">{{ number_format((float) $item->amount, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(count($funds) > 0)
    {{-- Cassa previdenziale --}}
    <table class="total">
        <tr class="center border_bottom">
            <td style="width: 49%" class="border_right padding">Dati Cassa Previdenziale</td>
            <td style="width: 17%" class="border_right padding">Imponibile</td>
            <td style="width: 9%" class="border_right padding">%Contr.</td>
            <td style="width: 9%" class="border_right padding">Ritenuta</td>
            <td style="width: 9%" class="border_right padding">%IVA</td>
            <td style="width: 13%" class="border_right padding">Importo</td>
        </tr>
        @foreach ($funds as $fund)
        <tr>
            <td style="width: 49%" class="left padding">{{ $fund['fund'] }}</td>
            <td style="width: 17%" class="right padding">{{ number_format((float) $fund['taxable_base'], 2, ',', '.') }}</td>
            <td style="width: 9%" class="right padding">{{ number_format((float) $fund['rate'], 2, ',', '.') }}</td>
            <td style="width: 9%" class="right padding"></td>
            <td style="width: 9%" class="right padding">
                @if (is_numeric($fund['%']))
                    {{ number_format((float) $fund['%'], 2, ',', '.') }}
                @else
                    {{ $fund['%'] }}
                @endif
            </td>
            <td style="width: 13%" class="right padding">{{ number_format((float) $fund['amount'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- Riepilogo IVA --}}
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
                <td style="width: 9%" class="right padding">
                    @if (is_numeric($vat['%']))
                        {{ number_format((float) $vat['%'], 2, ',', '.') }}
                    @else
                        {{ $vat['%'] }}
                    @endif
                </td>
                <td style="width: 18%" class="padding"></td>
                <td style="width: 7%" class="padding"></td>
                <td style="width: 17%" class="right padding">{{ number_format((float) $vat['taxable'], 2, ',', '.') }}</td>
                <td style="width: 12%" class="right padding">{{ number_format((float) $vat['vat'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    {{-- Totali --}}
    @php
        $fundTotal = 0;
        $vatTotal = 0;
        if(count($funds) > 0)
            $fundTotal = array_sum(array_column($funds, 'amount'));
        $vatTotal = array_sum(array_column($funds, 'vat'));
        $total = $invoice->total + $fundTotal + $vatTotal;
    @endphp
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
            <td style="width: 19%" class="right padding">{{ $invoice->company->stampDuty->virtual_stamp ? number_format((float) $invoice->company->stampDuty->virtual_amount, 2, ',', '.')  : ''}}</td>
            <td style="width: 19%" class="center padding">{{ $invoice->company->stampDuty->virtual_stamp ? 'SI' : ''}}</td>
            <td style="width: 32%" class="padding"></td>
            <td style="width: 5%" class="padding"></td>
            <td style="width: 25%" class="right padding bold">{{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
        </tr>
    </table>

    @php
        use App\Enums\WithholdingType;
        $selectedIds = is_array($invoice->withholdings) ? $invoice->withholdings : [];
        $withholdings = $invoice->company->withholdings->filter(function ($item) use ($selectedIds) {
            return in_array($item->id, $selectedIds);
        });
        $accontoValues = [
            WithholdingType::RT01,                               // Ritenuta d'acconto (persone fisiche)
            WithholdingType::RT02,                               // Ritenuta d'acconto (persone giuridiche)
        ];
        $hasWithholdingTax = collect($withholdings)
            ->search(fn($withholding) => in_array($withholding->withholding_type, $accontoValues));
        $withholdingAmount = 0;
    @endphp
    @if(count($invoice->company->withholdings) > 0 && $hasWithholdingTax !== false &&
            !in_array($invoice->client->subtype, [ \App\Enums\ClientSubtype::MAN, \App\Enums\ClientSubtype::WOMAN, ]))
        {{-- Ritenuta --}}
        @php
            $taxable = $invoice->getTaxable();
            $withholdingAmount = $taxable * ($invoice->company->withholdings[$hasWithholdingTax]->rate / 100);
        @endphp
        <table class="total">
            <tr class="center border_bottom">
                <td style="width: 48%" class="border_right padding">Dati ritenuta d'acconto</td>
                <td style="width: 7%" class="border_right padding">Aliquota ritenuta</td>
                <td style="width: 30%" class="border_right padding">Causale</td>
                <td style="width: 15%" class="border_right padding">Importo</td>
            </tr>
            <tr>
                <td style="width: 48%" class="left padding">{{ $invoice->company->withholdings[$hasWithholdingTax]->withholding_type->getPrint() }}</td>
                <td style="width: 7%" class="right padding">{{ number_format((float) $invoice->company->withholdings[$hasWithholdingTax]->rate, 2, ',', '.') }}</td>
                <td style="width: 30%" class="left padding">{{ $invoice->company->withholdings[$hasWithholdingTax]->payment_reason->getCausal() }}</td>
                <td style="width: 15%" class="right padding">{{ number_format((float) $withholdingAmount, 2, ',', '.') }}</td>
            </tr>
        </table>
    @endif

    {{-- Pagamento --}}
    @php
        $totalPay = $invoice->total - $withholdingAmount;
    @endphp
    <table>
        <tr class="center border_bottom">
            <td style="width: 29%" class="border_right padding">Modalità pagamento</td>
            <td style="width: 25%" class="border_right padding">Coordinate bancarie</td>
            <td style="width: 25%" class="border_right padding">Istituto</td>
            <td style="width: 11%" class="border_right padding">Data scadenza</td>
            <td style="width: 10%" class="border_right padding">Importo</td>
        </tr>
        <tr>
            <td style="width: 29%" class="left padding">{{ $invoice->payment_type?->getCode() }} {{ $invoice->payment_type?->getLabel() }}</td>
            <td style="width: 25%" class="left padding">{{ $invoice->bankAccount?->iban ? 'IBAN ' . $invoice->bankAccount->iban : '' }}</td>
            <td style="width: 25%" class="left padding">{{ $invoice->bankAccount?->name }}</td>
            <td style="width: 11%" class="center padding">{{ $invoice->invoice_date->addDays($invoice->payment_days)->format('d-m-Y') }}</td>
            <td style="width: 10%" class="right padding">{{ number_format((float) $totalPay, 2, ',', '.') }}</td>
        </tr>
    </table>
</body>
</html>
