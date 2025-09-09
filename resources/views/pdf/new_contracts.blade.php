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
        padding: 4px;
        text-align: left;
        border-left: none;
        border-right: none;
    }
    thead th {
        border-top: 2px solid black;
        border-bottom: 2px solid black;
    }
    table thead tr:first-child th {
        border-bottom: none;
    }
    table thead tr:nth-child(2) th {
        border-top: none;
    }
    tr td:first-child,
    tr th:first-child {
        border-left: 2px solid black;
    }
    tr td:last-child,
    tr th:last-child {
        border-right: 2px solid black;
    }
    .record-row-first {
        border-bottom: none;
    }
    .record-row-second {
        border-top: none;
        border-bottom: none;
    }
    .record-row-last-detail {
        border-bottom: 1px dashed black;
    }
    tbody tr:last-child {
        border-bottom: 2px solid black;
    }
    .description-cell {
        padding: 4px;
    }
    .page-number {
        position: fixed;
        bottom: 10px;
        right: 10px;
        font-size: 10px;
        text-align: right;
        width: 100%;
    }
    .page-number::after {
        content: "Pagina " counter(page);
    }
</style>
</head>
<body>
    <div class="page-number"></div>
    <h2 style="text-align: center"><u>Elenco Contratti</u></h2>
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @if($search)
                <li> Ricerca: {{ $search }} </li>
            @endif
            @php
                $fieldTranslations = [
                    'client_id' => 'Cliente',
                    'tax_types' => 'Entrate',
                    'accrual_types' => 'Competenze', // MODIFICA: Cambiato da 'accrual_type_id' a 'accrual_types'
                    'payment_type' => 'Tipo pagamento'
                ];
                $fieldValues = [
                    'tax_types' => [
                        'cds' => 'Codice della Strada',
                        'ici' => 'Imposta Comunale sugli Immobili',
                        'imu' => 'Imposta Municipale Unica',
                        'libero' => 'Libera',
                        'park' => 'Parcheggio',
                        'pub' => "Imposta sulla Pubblicita'",
                        'tari' => 'Tassa sui Rifiuti',
                        'tep' => 'TEP',
                        'tosap' => 'Tassa per l\'Occupazione del Suolo Pubblico',
                        '' => '',
                    ],
                    'payment_type' => [
                        'aggio' => 'Aggio',
                        'servizio' => 'Servizio',
                        'canone' => 'Canone'
                    ],
                    'accrual_types' => \App\Models\AccrualType::pluck('name', 'id')->toArray(), // MODIFICA: Aggiunto per accrual_types
                ];
            @endphp
            @foreach($filters as $field => $data)
                @if(!empty($data['values']) || !empty($data['value']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        @if($field == 'client_id')
                            @php
                                $client = !empty($data['value']) ? \App\Models\Client::find($data['value']) : null;
                            @endphp
                            {{ $client->denomination ?? 'N/A' }}
                        @elseif($field == 'accrual_types') <!-- MODIFICA: Cambiato da 'accrual_type_id' a 'accrual_types' -->
                            @php
                                $val = [];
                                $values = $data['values'] ?? ($data['value'] ? [$data['value']] : []);
                                foreach($values as $el) {
                                    $val[] = $fieldValues['accrual_types'][$el] ?? \App\Models\AccrualType::find($el)?->name ?? $el;
                                }
                            @endphp
                            {{ implode(', ', $val) ?: 'N/A' }}
                        @elseif($field == 'tax_types')
                            @php
                                $val = [];
                                foreach($data['values'] as $el) {
                                    $val[] = $fieldValues['tax_types'][$el] ?? \App\Enums\TaxType::from($el)->getLabel();
                                }
                            @endphp
                            {{ implode(', ', $val) ?: 'N/A' }}
                        @else
                            @php
                                $val = [];
                                foreach($data['values'] as $el) {
                                    $val[] = $fieldValues[$field][$el] ?? $el;
                                }
                            @endphp
                            {{ implode(', ', $val) ?: 'N/A' }}
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Entrata</th>
                <th>Competenza</th>
                <th>Tipo pagamento</th>
                <th>Inizio contratto</th>
                <th>Fine contratto</th>
                <th>Importo</th>
            </tr>
            <tr>
                <th>Numero</th>
                <th>Tipo contratto</th>
                <th>Data contratto</th>
                <th>Descrizione</th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($contracts as $contract)
                <tr class="record-row-first">
                    <td>{{ $contract->client->denomination }}</td>
                    <td>{{ !empty($contract->tax_types) ? implode(', ', $contract->tax_types) : 'N/A' }}</td>
                    <td>{{ !empty($contract->accrual_types) ? implode(', ', $contract->accrual_types) : 'N/A' }}</td> <!-- MODIFICA: Gestione di accrual_types -->
                    <td>{{ $contract->payment_type->getLabel() }}</td>
                    <td>{{ \Carbon\Carbon::parse($contract->start_validity_date)->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($contract->end_validity_date)->format('d/m/Y') }}</td>
                    <td>{{ number_format($contract->amount, 2, ',', '.') }} €</td>
                </tr>
                @if($contract->contractDetails->isEmpty())
                    <tr class="record-row-second record-row-last-detail">
                        <td colspan="7"></td>
                    </tr>
                @else
                    <tr class="record-row-second">
                        <td colspan="7"><u>Storico contratto:</u></td>
                    </tr>
                    @foreach ($contract->contractDetails as $index => $detail)
                        <tr class="record-row-second {{ $index === $contract->contractDetails->count() - 1 ? 'record-row-last-detail' : '' }}">
                            <td>{{ $detail->number }}</td>
                            <td>{{ $detail->contract_type->getLabel() }}</td>
                            <td>{{ \Carbon\Carbon::parse($detail->date)->format('d/m/Y') }}</td>
                            <td colspan="4" class="description-cell">{{ $detail->description }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>