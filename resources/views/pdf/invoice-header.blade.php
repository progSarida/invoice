{{-- pdf/invoice-header.blade.php --}}
<style>
    .fixed-header { position: fixed; top: 0; left: 0; right: 0; height: 55mm; background: white; padding-bottom: 5mm; border-bottom: 0.2px solid #000; font-size: 2.75mm; }

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

    /* .header { margin-bottom: 46px; } */
</style>

<div class="fixed-header">
    {{-- Dati SDI --}}
    <div class="dati_sdi" style="margin-bottom: 10px;">
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
</div>
