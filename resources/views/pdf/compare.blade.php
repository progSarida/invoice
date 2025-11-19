<!DOCTYPE html>
<html>
<head>
<style>
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 7pt;
    }
    table {
        border-collapse: collapse;
        width: 100%;
        border: 1px solid black;
    }
    th, td {
        padding: 1px 3px;
        text-align: left;
        border: none;
    }
    thead th {
        border-top: 1px solid black;
        border-bottom: 1px solid black;
        font-weight: bold;
        font-size: 7pt;
    }
    table thead tr:first-child th {
        border-bottom: none;
    }
    table thead tr:nth-child(2) th {
        border-top: none;
    }
    tr td:first-child, tr th:first-child {
        border-left: 1px solid black;
    }
    tr td:last-child, tr th:last-child {
        border-right: 1px solid black;
    }
    .record-row {
        /* border-bottom: 1px dashed black; */
    }
    .record-row:nth-child(32n) {
        page-break-after: always;
    }
    tbody tr:last-child {
        border-bottom: 1px solid black;
    }
    .page-number {
        position: fixed;
        bottom: 10px;
        right: 10px;
        font-size: 6pt;
        text-align: right;
        width: 100%;
    }
    .page-number::after {
        content: "Pag. " counter(page) " - " attr(data-date);
    }
    /* Bordo verticale per separare le due metà */
    th:nth-child(6), td:nth-child(6) {
        border-right: 1px solid black;
    }
    /* Allineamento a destra per la colonna Importo */
    th:nth-child(6), td:nth-child(6), th:nth-child(12), td:nth-child(12) {
        text-align: right;
    }
</style>
</head>
<body data-date="{{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}">
    <div class="page-number"></div>
    <h2 style="text-align: center"><u>Fatturazione Comparata</u></h2>
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @php
                $fieldTranslations = [
                    'accrual_year_1' => 'Anno competenza 1',
                    'accrual_year_2' => 'Anno competenza 2',
                    'doc_type_id' => 'Tipo documento',
                    'tax_type' => 'Entrata',
                    'client_id' => 'Cliente',
                    'manage_type_id' => 'Tipo di gestione',
                    'from_budget_year' => 'Anno bilancio da',
                    'to_budget_year' => 'Anno bilancio a',
                    'from_invoice_date' => 'Data fatturazione da',
                    'to_invoice_date' => 'Data fatturazione a',
                    'contract_type' => 'Tipo contratto',
                ];
                $fieldValues = [
                    'tax_type' => [
                        'cds' => \App\Enums\TaxType::from('cds')->getLabel(),
                        'ici' => \App\Enums\TaxType::from('ici')->getLabel(),
                        'imu' => \App\Enums\TaxType::from('imu')->getLabel(),
                        'libero' => \App\Enums\TaxType::from('libero')->getLabel(),
                        'park' => \App\Enums\TaxType::from('park')->getLabel(),
                        'pub' => \App\Enums\TaxType::from('pub')->getLabel(),
                        'tari' => \App\Enums\TaxType::from('tari')->getLabel(),
                        'tep' => \App\Enums\TaxType::from('tep')->getLabel(),
                        'tosap' => \App\Enums\TaxType::from('tosap')->getLabel(),
                        '' => '',
                    ],
                    'contract_type' => [
                        'fixed' => 'Fisso',
                        'variable' => 'Variabile',
                    ],
                    'doc_type_id' => [
                        '2' => 'Fattura',
                        // Aggiungi altri valori reali dal modello DocType
                    ],
                    'manage_type_id' => [
                        '3' => \App\Models\ManageType::find(3)?->name ?? 'Servizio',
                        // Aggiungi altri valori reali dal modello ManageType
                    ],
                ];
            @endphp
            @foreach($filters as $field => $value)
                @if(!empty($value))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        @if($field == 'client_id')
                            @php
                                $client = !empty($value) ? \App\Models\Client::find($value) : null;
                            @endphp
                            {{ $client ? ($client->comune ?? $client->denomination ?? 'N/D') : 'N/D' }}
                        @elseif($field == 'doc_type_id')
                            @php
                                $docType = !empty($value) ? \App\Models\DocType::find($value) : null;
                            @endphp
                            {{ $docType ? $docType->description : ($fieldValues['doc_type_id'][$value] ?? 'N/D') }}
                        @elseif($field == 'manage_type_id')
                            @php
                                $manageType = !empty($value) ? \App\Models\ManageType::find($value) : null;
                            @endphp
                            {{ $manageType ? $manageType->name : ($fieldValues['manage_type_id'][$value] ?? 'N/D') }}
                        @elseif($field == 'from_invoice_date' || $field == 'to_invoice_date')
                            {{ \Carbon\Carbon::parse($value)->format('d/m/Y') }}
                        @elseif(isset($fieldValues[$field]) && isset($fieldValues[$field][$value]))
                            {{ $fieldValues[$field][$value] }}
                        @else
                            {{ $value }}
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
    <table>
        <thead>
            <tr>
                <th width="6%">Comune</th>
                <th width="5%">Anno</th>
                <th width="9%">Riscossione</th>
                <th width="6%">Gestione</th>
                <th width="12%">Tipo</th>
                <th width="8%">Importo</th>
                <th width="6%">Comune</th>
                <th width="5%">Anno</th>
                <th width="9%">Riscossione</th>
                <th width="6%">Gestione</th>
                <th width="12%">Tipo</th>
                <th width="8%">Importo</th>
            </tr>
        </thead>
        <tbody>
            @php
                $maxRows = 0;
                foreach ($data as $comune => $years) {
                    $count1 = isset($years[1]) ? count($years[1]) : 0;
                    $count2 = isset($years[2]) ? count($years[2]) : 0;
                    $maxRows = max($maxRows, max($count1, $count2));
                }
            @endphp
            @foreach ($data as $comune => $years)
                @for ($i = 0; $i < $maxRows; $i++)
                    <tr class="record-row">
                        @if (isset($years[1][$i]))
                            <td width="6%">{{ $years[1][$i]['comune'] ?? 'N/D' }}</td>
                            <td width="5%">{{ $years[1][$i]['anno'] ?? 'N/D' }}</td>
                            <td width="9%">
                                @php
                                    $taxTypeLabel = !empty($years[1][$i]['tributo']) ? \App\Enums\TaxType::from($years[1][$i]['tributo'])->getLabel() : 'N/D';
                                @endphp
                                {{ $taxTypeLabel }}
                            </td>
                            <td width="6%">
                                @php
                                    $paymentTypeLabel = !empty($years[1][$i]['tipo_gestione']) ? \App\Enums\TenderPaymentType::from($years[1][$i]['tipo_gestione'])->getLabel() : 'N/D';
                                @endphp
                                {{ $paymentTypeLabel }}
                            </td>
                            <td width="12%">
                                @php
                                    $docType = !empty($years[1][$i]['tipo_fattura']) ? \App\Models\DocType::find($years[1][$i]['tipo_fattura']) : null;
                                @endphp
                                {{ $docType ? $docType->description : ($fieldValues['doc_type_id'][$years[1][$i]['tipo_fattura']] ?? 'N/D') }}
                            </td>
                            <td width="8%">{{ number_format($years[1][$i]['importo'], 2, ',', '.') }} €</td>
                        @else
                            <td width="6%"></td>
                            <td width="5%"></td>
                            <td width="9%"></td>
                            <td width="6%"></td>
                            <td width="12%"></td>
                            <td width="8%"></td>
                        @endif
                        @if (isset($years[2][$i]))
                            <td width="6%">{{ $years[2][$i]['comune'] ?? 'N/D' }}</td>
                            <td width="5%">{{ $years[2][$i]['anno'] ?? 'N/D' }}</td>
                            <td width="9%">
                                @php
                                    $taxTypeLabel = !empty($years[2][$i]['tributo']) ? \App\Enums\TaxType::from($years[2][$i]['tributo'])->getLabel() : 'N/D';
                                @endphp
                                {{ $taxTypeLabel }}
                            </td>
                            <td width="6%">
                                @php
                                    $paymentTypeLabel = !empty($years[2][$i]['tipo_gestione']) ? \App\Enums\TenderPaymentType::from($years[2][$i]['tipo_gestione'])->getLabel() : 'N/D';
                                @endphp
                                {{ $paymentTypeLabel }}
                            </td>
                            <td width="12%">
                                @php
                                    $docType = !empty($years[2][$i]['tipo_fattura']) ? \App\Models\DocType::find($years[2][$i]['tipo_fattura']) : null;
                                @endphp
                                {{ $docType ? $docType->description : ($fieldValues['doc_type_id'][$years[2][$i]['tipo_fattura']] ?? 'N/D') }}
                            </td>
                            <td width="8%">{{ number_format($years[2][$i]['importo'], 2, ',', '.') }} €</td>
                        @else
                            <td width="6%"></td>
                            <td width="5%"></td>
                            <td width="9%"></td>
                            <td width="6%"></td>
                            <td width="12%"></td>
                            <td width="8%"></td>
                        @endif
                    </tr>
                @endfor
                <tr>
                    <td colspan="12" style="border-bottom: 1px solid black;"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
