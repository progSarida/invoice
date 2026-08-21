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
            border: none;
            padding: 4px;
            text-align: left;
        }
        /* Le due righe di intestazione si leggono come un blocco unico: nessun bordo fra loro */
        thead tr:first-child th {
            border-top: 2px solid black;
        }
        thead tr:last-child th {
            border-bottom: 2px solid black;
        }
        /* Il tratteggio separa i blocchi di record, non le righe dello stesso record */
        tbody tr.record-start td {
            border-top: 1px dashed black;
        }
        tbody tr.record-detail td {
            padding-top: 0;
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
        $supplier = '';
        $supplierId = $filters['supplier_id']['value'] ?? null;
        if ($supplierId) {
            $supplier = \App\Models\Supplier::find($supplierId)?->denomination;
        }

        // Closure e non funzione: due viste che dichiarano la stessa funzione andrebbero in conflitto
        $euroFormat = fn ($value) => number_format((float) $value, 2, ',', '.') . ' €';
    @endphp
    <h2 style="text-align: center"><u>Elenco Pagamenti Passivi {{ $supplier }}</u></h2>
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif
            @php
                $fieldTranslations = [
                    'validated' => 'Validati',
                    'select_doc_type' => 'Tipo documento',
                    'exclude_doc_type' => 'Tipo documento escluso',
                    'withholdings' => 'Ritenuta d\'acconto',
                    'payment_date_range' => 'Pagamento',
                    'registration_date_range' => 'Registrazione',
                ];
                $fieldValues = [
                    'validated' => [
                        'si' => 'Sì',
                        'no' => 'No',
                    ],
                    'withholdings' => [
                        'yes' => 'Con ritenuta',
                        'no' => 'Senza ritenuta',
                    ],
                ];
                $docTypes = \App\Models\DocType::pluck('description', 'name')->toArray();
            @endphp
            @foreach($filters as $field => $data)
                @continue($field === 'supplier_id')
                @php
                    $label = $fieldTranslations[$field] ?? ucfirst($field);
                    $displayValues = [];

                    if (in_array($field, ['payment_date_range', 'registration_date_range'])) {
                        $prefix = $field === 'payment_date_range' ? 'payment' : 'registration';
                        $from = $data[$prefix . '_from_date'] ?? null;
                        $to = $data[$prefix . '_to_date'] ?? null;

                        if ($from && $to) {
                            $displayValues[] = 'dal ' . \Carbon\Carbon::parse($from)->format('d/m/Y')
                                . ' al ' . \Carbon\Carbon::parse($to)->format('d/m/Y');
                        } elseif ($from) {
                            $displayValues[] = 'dal ' . \Carbon\Carbon::parse($from)->format('d/m/Y');
                        } elseif ($to) {
                            $displayValues[] = 'al ' . \Carbon\Carbon::parse($to)->format('d/m/Y');
                        }
                    } elseif (!empty($data['values'])) {
                        foreach ($data['values'] as $val) {
                            $displayValues[] = $docTypes[$val] ?? $val;
                        }
                    } elseif (filled($data['value'] ?? null)) {
                        $value = $data['value'];
                        $displayValues[] = $fieldValues[$field][$value] ?? $docTypes[$value] ?? $value;
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
                <th colspan="2">Fattura (Tipo)</th>
                <th colspan="3">Fornitore</th>
            </tr>
            <tr>
                <th>Data fattura</th>
                <th>Note di variazione</th>
                <th>Importo</th>
                <th>Data</th>
                <th>Residuo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                @php
                    $invoice = $payment->passiveInvoice;
                    $notes = $invoice?->getNotesTotal() ?? 0;
                    $residue = $invoice?->getResidue() ?? 0;
                @endphp
                <tr class="record-start">
                    <td colspan="2"><strong>{{ $invoice?->number }} ({{ $invoice?->doc_type }})</strong></td>
                    <td colspan="3">{{ $invoice?->supplier?->denomination }}</td>
                </tr>
                <tr class="record-detail">
                    <td>{{ $invoice?->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ $euroFormat($notes) }}</td>
                    <td>{{ $euroFormat($payment->amount) }}</td>
                    <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ $euroFormat($residue) }}</td>
                </tr>
                <tr class="record-detail">
                    <td colspan="5"><strong>Note:</strong> {{ $payment->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
