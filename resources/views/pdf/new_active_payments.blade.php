<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            border: 2px solid black;                          /* bordi esterni */
        }

        th, td {
            border-top: 1px dashed black;                     /* tratteggio tra le righe */
            border-bottom: 1px dashed black;                  /* tratteggio tra le righe */
            border-left: none;                                  /* nessun bordo verticale */
            border-right: none;                                 /* nessun bordo verticale */
            padding: 4px;
            text-align: left;
        }

        thead th {
            border-top: 2px solid black;                      /* bordi intestazione sopra */
            border-bottom: 2px solid black;                   /* bordi intestazione sotto */
        }

        tbody tr:last-child td {
            border-bottom: 2px solid black;                   /* bordi esterni sotto */
        }

        tr td:first-child,
        tr th:first-child {
            border-left: 2px solid black;                     /* bordi esterni sinistra */
        }

        tr td:last-child,
        tr th:last-child {
            border-right: 2px solid black;                    /* bordi esterni destra */
        }
    </style>
</head>
<body>
    @php
        $client = '';
        $clientId = $filters['invoice_client_id']['value'] ?? null;

        if ($clientId) {
            $client = \App\Models\Client::find($clientId)?->denomination;
        }
    @endphp
    <h2 style="text-align: center"><u>Elenco Pagamenti {{ $client }}</u></h2>
    @php
        function euroFormat($value) {
            return number_format($value, 2, ',', '.') . ' €';
        }
    @endphp
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif
            @php
                // dd($filters);
                $fieldTranslations = [
                    'invoice_number' => 'Numero fattura',
                    'invoice_tax_type' => 'Entrata',
                    'validated' => 'Pagamenti validati',
                    'contract_accrual_type_id' => 'Competenza',
                    'invoice_tax_type' => 'Entrata',
                    'invoice_year' => 'Anno fattura',
                    'invoice_budget_year' => 'Anno bilancio',
                    'invoice_accrual_year' => 'Anno competenza',
                    'invoice_client_type' => 'Tipo cliente',
                    // 'invoice_client_id' => 'Cliente',
                ];
                $fieldValues = [
                    'invoice_client_type' => [
                        'private' => 'Privato',
                        'public' => 'Pubblica Amministrazione',
                    ],
                    'validated' => [
                        'si' => 'Si',
                        'no' => 'No'
                    ],
                    'invoice_tax_type' => [
                        'cds' => 'Codice della Strada',
                        'ici' => 'Imposta Comunale sugli Immobili',
                        'imu' => 'Imposta Municipale Unica',
                        'libero' => 'Libera',
                        'park' => 'Parcheggio',
                        'pub' => 'Imposta sulla Pubblicità',
                        'tari' => 'Tassa sui Rifiuti',
                        'tep' => 'TEP',
                        'tosap' => 'Tassa per l\'Occupazione del Suolo Pubblico',
                    ],
                    'contract_accrual_type_id' => \App\Models\AccrualType::pluck('name', 'id')->toArray(),
                    // 'invoice_client_id' => \App\Models\Client::pluck('denomination', 'id')->toArray(),
                ];
            @endphp
            @foreach($filters as $field => $data)
                @continue($field === 'invoice_client_id')
                @php
                    $label = $fieldTranslations[$field] ?? ucfirst($field);
                    $displayValues = [];

                    if (isset($data['number'])) {
                        // Campo "numero fattura"
                        $displayValues[] = sprintf('%03d', $data['number']);
                    } elseif (isset($data['value'])) {
                        $value = $data['value'];
                        if ($value === '') {
                            $displayValues[] = 'Tutti';
                        } elseif (isset($fieldValues[$field][$value])) {
                            $displayValues[] = $fieldValues[$field][$value];
                        } elseif (\App\Enums\ClientType::tryFrom($value)?->getLabel()) {
                            $displayValues[] = \App\Enums\ClientType::tryFrom($value)?->getLabel();
                        } else {
                            $displayValues[] = $value;
                        }
                    } elseif (isset($data['values'])) {
                        foreach ($data['values'] as $val) {
                            if (isset($fieldValues[$field][$val])) {
                                $displayValues[] = $fieldValues[$field][$val];
                            } else {
                                $displayValues[] = $val;
                            }
                        }
                    }
                @endphp

                @if (!empty($displayValues))
                    <li>{{ $label }}: {{ implode(', ', $displayValues) }}</li>
                @endif
            @endforeach
        </ul>
    @endif
    <table>
        <thead>
            <tr>
                <th>Fattura (Tipo)</th>
                <th>CC</th>
                <th>Cliente</th>
                <th>Data fattura</th>
                <th>Fattura</th>
                <th>Nota credito</th>
                <th>Importo</th>
                <th>Data</th>
                <th>Residuo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                @php
                    $notes = $payment->invoice->creditNotes?->sum('total') ?? 0;
                    $payments = $payment->invoice->activePayments?->sum('amount') ?? 0;
                    $residue = $payment->invoice->total - ($notes + $payments);
                @endphp
                <tr>
                    <td>{{ $payment->invoice->getNewInvoiceNumber() }} ({{$payment->invoice->docType->name}})</td>
                    <td>{{ $payment->invoice->client->city->code }}</td>
                    <td>{{ $payment->invoice->client->denomination }}</td>
                    <td>{{ \Carbon\Carbon::parse($payment->invoice->invoice_date)->format('d/m/Y') }}</td>
                    <td>{{ euroFormat($payment->invoice->total) }}</td>
                    <td>{{ euroFormat($notes) }}</td>
                    <td>{{ euroFormat($payment->amount) }}</td>
                    <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</td>
                    <td>{{ euroFormat($residue) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
