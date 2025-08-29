<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Contratto {{ $contract->lastDetail->number ?? 'N/A' }} - {{ $contract->lastDetail->date->format('d/m/Y') ?? 'N/A' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 2.75mm;
            margin: 0;
            padding: 20mm;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 5mm;
            margin: 0 0 10px 0;
            font-weight: bold;
        }

        .client {
            font-size: 5mm;
            margin: 0 0 10px 0;
            font-weight: bold;
        }

        .contract-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 4mm;
            font-weight: bold;
            background-color: #f5f5f5;
            padding: 5px 10px;
            border-left: 4px solid #333;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 0.5px solid #333;
            margin-bottom: 20px;
        }

        th, td {
            border: 0.3px solid #666;
            padding: 8px 12px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 3mm;
        }

        td {
            font-size: 2.75mm;
        }

        .label-col {
            width: 30%;
            font-weight: bold;
            background-color: #fafafa;
        }

        .value-col {
            width: 70%;
        }

        .amount {
            /* font-weight: bold; */
            /* font-size: 3.5mm; */
            /* color: #d63384; */
        }

        .dates {
            /* background-color: #e7f3ff; */
        }

        .codes {
            /* background-color: #fff3cd; */
        }

        .footer {
            position: fixed;
            bottom: 20mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            font-size: 2.5mm;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>CONTRATTO {{ strtoupper($contract->tax_type->getLabel()) }}</h1>
        <p class='client'>{{ $contract->client->denomination ?? 'Cliente N/A' }}</p>
    </div>

    <!-- Informazioni Generali -->
    <div class="section">
        <div class="section-title">Informazioni Generali</div>
        <table>
            {{-- <tr>
                <td class="label-col">ID Contratto</td>
                <td class="value-col">{{ $contract->id }}</td>
            </tr> --}}
            <tr>
                <td class="label-col">Cliente</td>
                <td class="value-col">{{ $contract->client->denomination ?? 'N/A' }}</td>
            </tr>
            <tr class="codes">
                <td class="label-col">Codice Ufficio</td>
                <td class="value-col">{{ $contract->office_code ?? 'Non specificato' }}</td>
            </tr>
            <tr class="codes">
                <td class="label-col">Nome Ufficio</td>
                <td class="value-col">{{ $contract->office_name ?? 'Non specificato' }}</td>
            </tr>
            <tr>
                <td class="label-col">Entrata</td>
                <td class="value-col">{{ strtoupper($contract->tax_type->getLabel()) }}</td>
            </tr>
            <tr class="codes">
                <td class="label-col">Codice CIG</td>
                <td class="value-col">{{ $contract->cig_code ?? 'Non specificato' }}</td>
            </tr>
            <tr class="codes">
                <td class="label-col">Codice CUP</td>
                <td class="value-col">{{ $contract->cup_code ?? 'Non specificato' }}</td>
            </tr>
            <tr>
                <td class="label-col">Competenza</td>
                <td class="value-col">{{ $contract->accrualType->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label-col">Tipo di Pagamento</td>
                <td class="value-col">{{ ucfirst($contract->payment_type->getLabel()) }}</td>
            </tr>
            <tr>
                <td class="label-col">Capienza</td>
                <td class="value-col amount">€ {{ number_format($contract->amount, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label-col">Rifatturazione</td>
                <td class="value-col">{{ $contract->reinvoice ? 'Sì' : 'No' }}</td>
            </tr>
            <tr class="dates">
                <td class="label-col">Data Inizio</td>
                <td class="value-col">{{ \Carbon\Carbon::parse($contract->start_validity_date)->format('d/m/Y') }}</td>
            </tr>
            <tr class="dates">
                <td class="label-col">Data Fine</td>
                <td class="value-col">{{ \Carbon\Carbon::parse($contract->end_validity_date)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label-col">Ciclo di Fatturazione</td>
                <td class="value-col">{{ ucfirst($contract->invoicing_cycle->getLabel()) }}</td>
            </tr>
        </table>
    </div>

    <!-- Informazioni Documento -->
    <div class="section">
        <div class="section-title">Affidamento</div>
        <table>
            <tr>
                <td class="label-col">Tipo</td>
                <td class="value-col">
                    {{ $contract?->lastDetail?->contract_type?->getLabel() ?? '' }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Numero</td>
                <td class="value-col">{{ $contract->lastDetail->number ?? '' }}</td>
            </tr>
            <tr>
                <td class="label-col">Data</td>
                <td class="value-col">
                    {{ $contract?->lastDetail?->date ? \Carbon\Carbon::parse($contract?->lastDetail?->date)->format('d/m/Y') : '' }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Documento generato il {{ now()->format('d/m/Y H:i:s') }} | Operatore: {{ Auth::user()->name }}</p>
    </div>
</body>
</html>
