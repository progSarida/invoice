<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* Essenziale per il controllo delle colonne */
            border: 2px solid black;
        }

        /* Forza la ripetizione dell'header */
        thead {
            display: table-header-group;
        }

        th, td {
            padding: 5px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            border: none;
        }

        /* Stili Header */
        thead th {
            background-color: #eeeeee;
            font-size: 10px;
            text-transform: uppercase;
        }

        .first-header {
            border-bottom: 1px dashed gray;
        }

        .second-header {
            border-bottom: 1px solid black;
        }

        /* Bordi dei record */
        .record-separator td {
            border-bottom: 1px dashed black;
        }

        /* L'ultimo record non deve avere il tratteggiato perché c'è il bordo tabella */
        tbody tr:last-child td {
            border-bottom: none;
        }

        .description-cell {
            background-color: #fafafa;
            font-style: italic;
            padding: 4px 10px;
        }

        .text-right { text-align: right; }

        .page-number {
            position: fixed; bottom: 10px; right: 10px; font-size: 10px;
        }
        .page-number::after { content: "Pagina " counter(page); }
    </style>
</head>
<body>
    <div class="page-number"></div>
    <h2 style="text-align: center; text-decoration: underline;">Elenco Fatture Passive</h2>

    @if(!empty($filters))
        <div style="margin-bottom: 15px;">
            <strong>Filtri applicati:</strong>
            <ul style="margin-top: 5px;">
                @if($search) <li>Ricerca: {{ $search }}</li> @endif
                @php
                    $fieldTranslations = [
                        'supplier_id' => 'Fornitore',
                        'doc_type' => 'Tipo documento',
                        'exclude_doc_types' => 'Escludi tipo documento',
                        'pi_validation_status' => 'Validazione',
                        'paid' => 'Pagamento',
                    ];
                    $fieldValues = [
                        'paid' => ['si' => 'Totale', 'par' => 'Parziale', 'no' => 'Nessuno'],
                        'pi_validation_status' => [
                            'validati' => 'Tutti validati', 'no_status' => 'Non validata',
                            'ok' => 'Validata', 'wait' => 'Da verificare',
                            'block' => 'Bloccata', 'view' => 'Da visionare'
                        ],
                    ];
                @endphp
                @foreach($filters as $field => $data)
                    @if(!empty($data['values']) || !empty($data['value']))
                        <li>
                            {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                            @if($field == 'supplier_id')
                                {{ \App\Models\Supplier::find($data['value'])?->denomination ?? 'N/D' }}
                            @elseif(in_array($field, ['doc_type', 'exclude_doc_types']))
                                @php
                                    $names = collect($data['values'])->map(fn($n) => \App\Models\DocType::where('name', $n)->first()?->description ?? $n);
                                @endphp
                                {{ $names->implode(', ') }}
                            @else
                                {{ $fieldValues[$field][$data['value']] ?? $data['value'] }}
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    <table>
        <thead>
            {{-- Riga Header 1 --}}
            <tr class="first-header">
                <th colspan="7">Tipo Documento</th>
                <th colspan="8">Numero</th>
                <th colspan="5">Data</th>
            </tr>
            {{-- Riga Header 2 --}}
            <tr class="second-header">
                <th colspan="7">Fornitore</th>
                <th colspan="3" class="text-right">Dovuto</th>
                <th colspan="3" class="text-right">Pagato</th>
                <th colspan="3">Scadenza</th>
                <th colspan="4">Validazione</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
                {{-- Riga 1 Dati --}}
                <tr>
                    <td colspan="7"><strong>{{ $invoice->docType?->description ?? 'N/D' }}</strong></td>
                    <td colspan="8">{{ $invoice->number }}</td>
                    <td colspan="5">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                </tr>
                {{-- Riga 2 Dati --}}
                <tr class="{{ !$invoice->description ? 'record-separator' : '' }}">
                    <td colspan="7">{{ $invoice->supplier?->denomination ?? 'N/D' }}</td>
                    <td colspan="3" class="text-right">{{ number_format($invoice->total, 2, ',', '.') }} €</td>
                    <td colspan="3" class="text-right">{{ number_format($invoice->total_payment, 2, ',', '.') }} €</td>
                    <td colspan="3">{{ $invoice->payment_deadline ? \Carbon\Carbon::parse($invoice->payment_deadline)->format('d/m/Y') : '-' }}</td>
                    <td colspan="4">{{ $invoice->piValidation?->name ?? 'Non validata' }}</td>
                </tr>
                {{-- Riga Descrizione --}}
                @if($invoice->description)
                    <tr class="record-separator">
                        <td colspan="20" class="description-cell">
                            {{ trim($invoice->description) }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
