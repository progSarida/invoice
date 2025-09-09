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
        border: 2px solid black; /* Bordi esterni continui */
    }

    th, td {
        padding: 4px;
        text-align: left;
        border-left: none; /* Nessun bordo verticale */
        border-right: none; /* Nessun bordo verticale */
    }

    thead th {
        border-top: 2px solid black; /* Bordi intestazione sopra */
        border-bottom: 2px solid black; /* Bordi intestazione sotto */
    }

    /* Aumenta la specificità per il primo <tr> della <thead> */
    table thead tr:first-child th {
        border-bottom: none; /* Rimuove il bordo inferiore del primo <tr> */
    }

    /* Aumenta la specificità per il secondo <tr> della <thead> */
    table thead tr:nth-child(2) th {
        border-top: none; /* Rimuove il bordo superiore del secondo <tr> */
    }

    /* Prima colonna con bordo sinistro */
    tr td:first-child,
    tr th:first-child {
        border-left: 2px solid black; /* Bordo esterno sinistra */
    }

    /* Ultima colonna con bordo destro */
    tr td:last-child,
    tr th:last-child {
        border-right: 2px solid black; /* Bordo esterno destra */
    }

    /* Prima riga di ogni record (contract) */
    .record-row-first {
        border-bottom: none; /* Nessun bordo inferiore per evitare sovrapposizioni */
    }

    /* Riga dei dettagli (contractDetails) */
    .record-row-second {
        border-top: none; /* Nessun bordo superiore */
        border-bottom: none; /* Nessun bordo inferiore */
    }

    /* Bordo tratteggiato dopo ogni blocco di contract */
    .record-row-last-detail {
        border-bottom: 1px dashed black; /* Bordo tratteggiato per separare i blocchi di contract */
    }

    /* Ultima riga della tabella */
    tbody tr:last-child {
        border-bottom: 2px solid black; /* Bordo esterno inferiore continuo */
    }

    /* Cella della descrizione */
    .description-cell {
        padding: 4px;
    }

    /* Numero di pagina in fondo */
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
                    'tax_types' => 'Entrate', // MODIFICA: Cambiato da 'tax_type' a 'tax_types'
                    'accrual_type_id' => 'Competenza',
                    'payment_type' => 'Tipo pagamento'
                ];
                $fieldValues = [
                    'tax_types' => [ // MODIFICA: Cambiato da 'tax_type' a 'tax_types'
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
                            {{ $client->denomination }}
                        @elseif($field == 'accrual_type_id')
                            @php
                                $type = !empty($data['value']) ? \App\Models\AccrualType::find($data['value']) : null;
                            @endphp
                            {{ $type->name }}
                        @elseif($field == 'tax_types') <!-- MODIFICA: Cambiato da 'tax_type' a 'tax_types' -->
                            @php
                                $val = [];
                                foreach($data['values'] as $el) {
                                    $val[] = $fieldValues['tax_types'][$el] ?? \App\Enums\TaxType::from($el)->getLabel();
                                }
                            @endphp
                            {{ implode(', ', $val) }}
                        @else
                            @php
                                $val = [];
                                foreach($data['values'] as $el) {
                                    $val[] = $fieldValues[$field][$el] ?? $el;
                                }
                            @endphp
                            {{ implode(', ', $val) }}
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
                    <td>{{ !empty($contract->tax_types) ? implode(', ', $contract->tax_types) : 'N/A' }}</td> <!-- MODIFICA: Gestione di tax_types -->
                    <td>{{ \App\Models\AccrualType::find($contract->accrual_type_id)->name }}</td>
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
