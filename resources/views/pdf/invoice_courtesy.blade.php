<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Fattura {{ $invoice->printNumber() }}</title>
    <style>
        /* table { width: 100%; border: 1px solid black; }
        td { border: 1px solid black; border-left: 1px solid black; border-right: 1px solid black; } */
        @page { margin: 1cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9pt; color: #333; line-height: 1.4; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: top; width: 50%; }
        .title { text-align: right; text-transform: uppercase; }
        /* .section-title { background-color: #f2f2f2; font-weight: bold; padding: 5px; text-align: center; text-transform: uppercase; margin-top: 15px; border-bottom: 1px solid #ccc; } */
        .section-title { font-weight: bold; padding: 5px; text-align: center; text-transform: uppercase; margin-top: 15px; border-bottom: 1px solid #ccc; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .data-table th { background-color: #e6e6e6; padding: 5px; font-size: 8pt; text-align: left; border: 1px solid #ddd; }
        .data-table td { padding: 5px; border: 1px solid #ddd; vertical-align: middle; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .summary-container { margin-top: 20px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { vertical-align: top; }

        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 3px 5px; border-bottom: 1px solid #eee; }
        .netto-pagare { background-color: #e6e6e6; font-size: 10pt; }
        .cliente { background-color: #f1f0f0;}

        .dot { height: 8px; width: 8px; background-color: #777; border-radius: 50%; display: inline-block; margin-right: 5px; vertical-align: middle; }

        .footer { position: fixed; bottom: 0; width: 100%; font-size: 7pt; text-align: center; color: #777; }
        .logo-aruba { color: #d9232d; font-weight: bold; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 50%;"></td>
            <td style="width: 50%; font-size: 7pt;" class="text-right">
                <strong class="title" style="font-size: 8pt;">Fattura</strong><br>
                nr. {{ $invoice->getInvoiceNumber() }} del {{ $invoice->invoice_date->format('d/m/Y') }}<br>
                Data invio: {{ $invoice->invoice_date->format('d/m/Y') }}
            </td>
        </tr>
        {{-- <tr>
            <td style="width: 50%;"></td>
            <td style="font-size: 7pt; width: 50%;" class="text-right">nr. {{ $invoice->getInvoiceNumber() }} del {{ $invoice->invoice_date->format('d/m/Y') }}<br></td>
        </tr>
        <tr>
            <td style="width: 50%;"></td>
            <td style="font-size: 7pt; width: 50%;" class="text-right"></td>
        </tr> --}}
        <tr>
            <td style="width: 50%;"><div style="font-weight: bold; font-size: 9pt; text-transform: uppercase;">Fornitore</div></td>
            <td  style="width: 50%;" class="cliente"><div style="font-weight: bold; font-size: 9pt; text-transform: uppercase;">Cliente</div></td>
        </tr>
        {{-- <tr>
            <td style="width: 50%;"><strong>{{ $invoice->company->name ?? 'Azienda Prova SpA' }}</strong><br></td>
            <td style="width: 50%;"><strong>{{ $invoice->client->denomination }}</strong><br></td>
        </tr>
        <tr>
            <td style="width: 50%; font-size: 7pt;">P.IVA: {{ $invoice->company->vat_number ?? '-' }}<br></td>
            <td style="width: 50%; font-size: 7pt;">P.IVA: {{ $invoice->client->vat_number ?? '-' }}<br></td>
        </tr>
        <tr>
            <td style="width: 50%; font-size: 7pt;">C.F.: {{ $invoice->company->tax_code ?? '-' }}<br></td>
            <td style="width: 50%; font-size: 7pt;">C.F.: {{ $invoice->client->tax_code ?? '-' }}<br></td>
        </tr>
        <tr>
            <td style="width: 50%; font-size: 7pt;">{{ $invoice->company->address }}<br></td>
            <td style="width: 50%; font-size: 7pt;">{{ $invoice->client->address }}<br></td>
        </tr>
        <tr>
            <td style="width: 50%; font-size: 7pt;">{{ $invoice->company->city->zip_code }} - {{ $invoice->company->city->name }} ({{ $invoice->company->city->province->code }}) - {{ $invoice->company->state->name }}</td>
            <td style="width: 50%; font-size: 7pt;">{{ $invoice->client->zip_code }} - {{ $invoice->client->city->name }} ({{ $invoice->client->city->province->code }}) - {{ $invoice->client->state->name }}</td>
        </tr>
        <tr>
            <td style="width: 50%;">{{ $invoice->company->email }}</td>
            <td style="width: 50%;"></td>
        </tr> --}}
        <tr>
        <td style="width: 50%; font-size: 8pt; line-height: 1.1;">
            <strong style="font-size: 8pt;">{{ $invoice->company->name ?? 'Azienda Prova SpA' }}</strong><br>
            @if($invoice->company->vat_number)P.IVA: {{ $invoice->company->vat_number }}<br>@endif
            @if($invoice->company->tax_code)C.F.: {{ $invoice->company->tax_code }}<br>@endif
            @if($invoice->company->address){{ $invoice->company->address }}<br>@endif
            @if($invoice->company->city){{ $invoice->company->city->zip_code }} - {{ $invoice->company->city->name }} ({{ $invoice->company->city->province->code }}), @if($invoice->company->state){{ $invoice->company->state->name }}@endif<br>@endif
            @if($invoice->company->email){{ $invoice->company->email }}@endif
        </td>
        <td style="width: 50%; font-size: 8pt; line-height: 1.1;" class="cliente">
            <strong style="font-size: 8pt;">{{ $invoice->client->denomination }}</strong><br>
            @if($invoice->client->vat_number)P.IVA: {{ $invoice->client->vat_number }}<br>@endif
            @if($invoice->client->tax_code)C.F.: {{ $invoice->client->tax_code }}<br>@endif
            @if($invoice->client->address){{ $invoice->client->address }}<br>@endif
            @if($invoice->client->city){{ $invoice->client->zip_code }} - {{ $invoice->client->city->name }} ({{ $invoice->client->city->province->code }}), @if($invoice->client->state){{ $invoice->client->state->name }}@endif<br>@endif
        </td>
    </tr>
    </table>

    <div class="section-title">Prodotti e Servizi</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">NR</th>
                <th style="width: 45%;">DESCRIZIONE</th>
                <th class="text-center">QUANTITÀ</th>
                <th class="text-right">PREZZO</th>
                <th class="text-center">SC/MG</th>
                <th class="text-right">IMPORTO</th>
                <th class="text-center">IVA</th>
                <th class="text-center">NATURA</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->invoiceItems as $index => $item)
            @continue($item->auto == true)
            @php
                $price = $item->quantity == null ? $item->amount : $item->unit_price;
            @endphp
            <tr style="font-size: 7pt;">
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-center">{{ $item->quantity ?? 0 }}</td>
                <td class="text-right">{{ number_format($price, 2, ',', '.') }} €</td>
                <td class="text-center">-</td>
                <td class="text-right">{{ number_format($item->amount, 2, ',', '.') }} €</td>
                <td class="text-center">{{ (int) $item->vat_code_type?->getRate() }} %</td>
                <td class="text-center">{{ $item->vat_code_type?->getCode() == '' ? '-' : $item->vat_code_type?->getCode()  }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @php
        $totalPay = $invoice->client->type == \App\Enums\ClientType::PUBLIC ? $invoice->no_vat_total : $invoice->total;
    @endphp
    <div class="section-title">Metodo di Pagamento</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>NR RATA</th>
                <th>METODO</th>
                <th>PAGAMENTO</th>
                <th>IBAN</th>
                <th>SCADENZA</th>
                <th class="text-right">IMPORTO</th>
            </tr>
        </thead>
        <tbody>
            <tr style="font-size: 7pt;">
                <td class="text-center">1</td>
                <td>{{ $invoice->payment_type->getLabel() }}</td>
                <td>{{ $invoice->payment_mode->getLabel() }}</td>
                <td>{{ $invoice->bankAccount->iban }}</td>
                <td>{{ $invoice->invoice_date->copy()->addDays($invoice->payment_days)->format('d/m/Y') }}</td>
                <td class="text-right">{{ number_format($totalPay, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td colspan="6" style="font-size: 7pt; border:: none;">Giorni temini pag.: {{ $invoice->payment_days }}, Data riferimento termini pag.: {{ $invoice->invoice_date->format('d/m/Y') }}</td>
            </tr>
        </tbody>
    </table>
    @if($invoice->contract || $invoice->description)
    <div class="section-title">Dati aggiuntivi</div>
    @endif
    @if($invoice->description)
    <table>
        <tr>
            <td style="font-size: 6pt;"><span class="dot"></span>DOCUMENTI CORRELATI</td>
        </tr>
    </table>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tipo doc.</th>
                <th>Numero doc.</th>
                <th>Data doc.</th>
                <th>CIG</th>
                <th>CUP</th>
            </tr>
        </thead>
        <tbody>
            <tr style="font-size: 7pt;">
                <td class="text-center">{{ $invoice->contractDetail ? $invoice->contractDetail->contract_type->getLabel() : '' }}</td>
                <td>{{ $invoice->contractDetail ? $invoice->contractDetail->number : '' }}</td>
                <td>{{ $invoice->contractDetail ? $invoice->contractDetail->date->format('d/m/Y') : '' }}</td>
                <td>{{ $invoice->contract ? $invoice->contract->cig_code : '' }}</td>
                <td>{{ $invoice->contract ? $invoice->contract->cup_code : '' }}</td>
            </tr>
        </tbody>
    </table>
    @endif
    @if($invoice->description)
    <table>
        <tr>
            <td style="font-size: 6pt;"><span class="dot"></span>CAUSALE DOCUMENTO</td>
        </tr>
        <tr>
            <td style="padding-left: 15px; font-size: 7pt;">{{ $invoice->description }}</td>
        </tr>
    </table>
    @endif
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td style="width: 60%; padding-right: 20px;">
                    <div style="font-weight: bold; margin-bottom: 5px; text-align: center;">RIEPILOGO IVA</div>
                    <table class="data-table" style="font-size: 8pt;">
                        <thead>
                            <tr>
                                <th>IVA</th>
                                <th>NATURA</th>
                                <th>NORMATIVA</th>
                                <th>ESIGIBILITÀ</th>
                                <th class="text-right">IMPONIBILE</th>
                                <th class="text-right">IMPOSTA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vats as $vat)
                            <tr>
                                <td style="text-align: right;">{{ (int)$vat['vat'] }}%</td>
                                <td style="text-align: center;">{{ is_integer($vat['%']) ? '' : $vat['%'] }}</td>
                                <td style="text-align: left; font-size: 8pt;">{{ !str_contains($vat['norm'] ?? '', '(') ? $vat['norm'] : '' }}</td>
                                <td style="text-align: left; font-size: 8pt;">{{ str_contains($vat['norm'] ?? '', '(') ? $vat['norm'] : '' }}</td>
                                <td style="text-align: right;">{{ number_format($vat['taxable'], 2, ',', '.') }} €</td>
                                <td style="text-align: right;">{{ number_format($vat['vat'], 2, ',', '.') }} €</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>

                <td style="width: 40%;">
                    <div style="font-weight: bold; margin-bottom: 5px; text-align: center;">CALCOLO FATTURA</div>
                    <table class="totals-table">
                        <tr>
                            <td style="font-size: 9pt;">Importo prodotti/servizi</td>
                            <td class="text-right">{{ number_format($invoice->total, 2, ',', '.') }} €</td>
                        </tr>
                        @foreach($funds as $fund)
                        <tr>
                            <td style="font-size: 9pt;">Cassa ({{ $fund['name'] }})</td>
                            <td class="text-right">{{ number_format($fund['amount'], 2, ',', '.') }} €</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td style="font-size: 9pt;">Totale imponibile</td>
                            <td class="text-right">{{ number_format($invoice->no_vat_total, 2, ',', '.') }} €</td>
                        </tr>
                        <tr>
                            <td style="font-size: 9pt;">Totale IVA</td>
                            <td class="text-right">{{ number_format($invoice->vat, 2, ',', '.') }} €</td>
                        </tr>
                        @php
                            $totalToPay = $invoice->client->type == \App\Enums\ClientType::PUBLIC ? $invoice->no_vat_total : $invoice->total;
                        @endphp
                        <tr class="netto-pagare">
                            <td>Netto a pagare</td>
                            <td style=" font-weight: bold; font-size: 11pt;" class="text-right">{{ number_format($totalToPay, 2, ',', '.') }} €</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Copia analogica della fattura elettronica inviata a SdI | Il documento xml originale è disponibile online sul portale "Fatture e Corrispettivi dell'Agenzia delle Entrate"</span>
    </div>

</body>
</html>
