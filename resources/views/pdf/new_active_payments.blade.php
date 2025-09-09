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
            border: 2px solid black;
        }
        th, td {
            border-top: 1px dashed black;
            border-bottom: 1px dashed black;
            border-left: none;
            border-right: none;
            padding: 4px;
            text-align: left;
        }
        thead th {
            border-top: 2px solid black;
            border-bottom: 2px solid black;
        }
        tbody tr:last-child td {
            border-bottom: 2px solid black;
        }
        tr td:first-child,
        tr th:first-child {
            border-left: 2px solid black;
        }
        tr td:last-child,
        tr th:last-child {
            border-right: 2px solid black;
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
                $fieldTranslations = [
                    'invoice_number' => 'Numero fattura',
                    'invoice_tax_type' => 'Entrata',
                    'validated' => 'Pagamenti validati',
                    'contract_accrual_types' => 'Competenze', // MODIFICA: Cambiato da 'contract_accrual_type_id' a 'contract_accrual_types'
                    'invoice_year' => 'Anno fattura',
                    'invoice_budget_year' => 'Anno bilancio',
                    'invoice_accrual_year' => 'Anno competenza',
                    'invoice_client_type' => 'Tipo cliente',
                ];
                $fieldValues = [
                    'invoice_client_type' => [
                        'private' => 'Privato',
                        'public' => 'Pubblica Amministrazione',
                    ],
                    'validated' => [
                        'si' => 'Sì',
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
                    'contract_accrual_types' => \App\Models\AccrualType::pluck('name', 'id')->toArray(), // MODIFICA: Cambiato da 'contract_accrual_type_id' a 'contract_accrual_types'
                ];
            @endphp
            @foreach($filters as $field => $data)
                @continue($field === 'invoice_client_id')
                @php
                    $label = $fieldTranslations[$field] ?? ucfirst($field);
                    $displayValues = [];
                    if (isset($data['number'])) {
                        $displayValues[] = sprintf('%03d', $data['number']);
                    } elseif ($field === 'contract_accrual_types') { // MODIFICA: Gestione specifica per contract_accrual_types
                        if (isset($data['values'])) {
                            foreach ($data['values'] as $val) {
                                $displayValues[] = $fieldValues['contract_accrual_types'][$val] ?? \App\Models\AccrualType::find($val)?->name ?? $val;
                            }
                        } elseif (isset($data['value'])) {
                            $displayValues[] = $fieldValues['contract_accrual_types'][$data['value']] ?? \App\Models\AccrualType::find($data['value'])?->name ?? $data['value'];
                        }
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