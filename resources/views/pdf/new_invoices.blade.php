<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
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

        /* Prima riga di ogni record */
        .record-row-first {
            border-bottom: none; /* Nessun bordo inferiore */
        }

        /* Secona riga di ogni record */
        .record-row-second {
            border-top: none; /* Nessun bordo superiore */
            border-bottom: none; /* Nessun bordo inferiore */
        }

        /* Terza riga di ogni record */
        .record-row-third {
            border-top: none; /* Nessun bordo superiore */
            border-bottom: 1px dashed black; /* Bordo tratteggiato per separare i record */
        }

        /* Ultima riga della tabella */
        tbody tr:last-child {
            border-bottom: 2px solid black; /* Bordo esterno inferiore continuo */
        }

        /* Cella della descrizione */
        .description-cell {
            padding: 4px;
        }

        /* Allineamento a destra per la colonna Totale e doversi (ottava colonna) */
        /*tr td:nth-child(8),
        tr th:nth-child(8) {
            text-align: right;
        }*/

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
    @php
        $client = '';
        $clientId = $filters['client_id']['value'] ?? null;

        if ($clientId) {
            $client = \App\Models\Client::find($clientId)?->denomination;
        }
    @endphp
    <div class="page-number"></div>
    <h2 style="text-align: center"><u>Elenco Fatture {{ $client }}</u></h2>
    @php
        function euroFormat($value) {
            return number_format($value, 2, ',', '.') . ' €';
        }
    @endphp
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @if($search)
                <li> Ricerca: {{ $search }} </li>
            @endif
            @php
                // dd($filters);
                $fieldTranslations = [
                    'doc_type_id' => 'Tipo',
                    'number' => 'Numero fattura',
                    'paid' => 'Fatture saldate',
                    'client_type' => 'Tipo cliente',
                    'client_id' => 'Cliente',
                    'tax_type' => 'Entrata',
                    'contract_id' => 'Contratto',
                    'sdi_status' => 'Status',
                    'accrual_type_id' => 'Tipo competenza',
                    'manage_type_id' => 'Tipo gestione',
                ];
                $fieldValues = [
                    'doc_type_id' => \App\Models\DocType::pluck('description', 'id')->toArray(),
                    'paid' => [
                        'si' => 'Si',
                        'no' => 'No'
                    ],
                    'client_type' => [
                        'private' => 'Privato',
                        'public' => 'Pubblica Amministrazione',
                    ],
                    'tax_type' => [
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
                    'sdi_status' => [
                        '' => '',
                        'da_inviare' => 'Da inviare',
                        'inviata' => 'Inviata',
                        'scartata' => 'NS - Notifica di scarto',
                        'consegnata' => 'RC - Ricevuta di consegna',
                        'mancata_consegna' => 'MC - Mancata consegna',
                        'accettata' => 'NE EC01 - Accettazione',
                        'rifiutata' => 'NE EC02 - Rifiuto',
                        'decorrenza_termini' => 'DT - Decorrenza termini',
                        'avvenuta_trasmissione' => "AT - Impossibilita' di recapito",
                        'metadata' => 'MT - Metadati',
                        'emessa' => 'AGYO - Fattura emessa',
                        'in_elaborazione' => 'AGYO - In elaborazione',
                        'rifiuto_validato' => 'Rifiuto validato',
                        'auto_inviata' => 'Auto inviata',
                        'fattura_aperta' => 'Fattura aperta',
                    ],
                    'accrual_type_id' => \App\Models\AccrualType::pluck('name', 'id')->toArray(),
                    'manage_type_id' => \App\Models\ManageType::pluck('name', 'id')->toArray(),
                ];

                $n = [];
            @endphp
            @foreach($filters as $field => $data)
                @continue(in_array($field, [
                    'client_id',
                    'invoice_year_from', 'invoice_year_to',
                    'invoice_budget_year_from', 'invoice_budget_year_to',
                    'invoice_accrual_year_from', 'invoice_accrual_year_to',
                ]))
                @if(!empty($data['number']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}: {{ str_pad($data['number'], 3, '0', STR_PAD_LEFT) }}
                    </li>
                @endif

                @if(!empty($data['value']) && empty($data['number']) && empty($data['values']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        @if($field == 'client_type')
                            @php
                                $type = !empty($data['value']) ? $fieldValues[$field][$data['value']] : null;
                            @endphp
                            {{ $type }}
                        @elseif($field == 'contract_id')
                            @php
                                $contract = !empty($data['value']) ? \App\Models\NewContract::find($data['value']) : null;
                            @endphp
                            {{$contract->office_name }} ({{ $contract->office_code }}) TIPO: {{ $contract->tax_type->getLabel() }} - CIG: {{ $contract->cig_code }}
                        @elseif($field == 'paid')
                            @php
                                $paid = !empty($data['value']) ? ($fieldValues['paid'][$data['value']] ?? $data['value']) : 'Si/No';
                            @endphp
                            {{ $paid }}
                        @endif
                    </li>
                @endif

                @if(!empty($data['values']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        @php
                            $val = [];
                            foreach($data['values'] as $el) {
                                $val[] = $fieldValues[$field][$el] ?? $el;
                            }
                        @endphp
                        {{ implode(', ', $val) }}
                    </li>
                @endif
            @endforeach
            @php
                $rangeFilters = [
                    'invoice_year' => [
                        'label' => 'Anno fattura',
                        'from' => $filters['invoice_year_from']['value'] ?? null,
                        'to' => $filters['invoice_year_to']['value'] ?? null,
                    ],
                    'invoice_budget_year' => [
                        'label' => 'Anno bilancio',
                        'from' => $filters['invoice_budget_year_from']['value'] ?? null,
                        'to' => $filters['invoice_budget_year_to']['value'] ?? null,
                    ],
                    'invoice_accrual_year' => [
                        'label' => 'Anno competenza',
                        'from' => $filters['invoice_accrual_year_from']['value'] ?? null,
                        'to' => $filters['invoice_accrual_year_to']['value'] ?? null,
                    ],
                ];
            @endphp

            @foreach($rangeFilters as $key => $range)
                @if($range['from'] || $range['to'])
                    <li>{{ $range['label'] }}: da {{ $range['from'] ?? '—' }} a {{ $range['to'] ?? '—' }}</li>
                @endif
            @endforeach
        </ul>
    @endif
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Descrizione</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>Tipo</th>
                <th></th>
                <th>Numero</th>
                <th></th>
                <th>Data</th>
                <th></th>
                <th>Cliente</th>
                <th></th>
                <th>Entrata</th>
                <th></th>
                <th>Tipo pagamento</th>
                <th></th>
                <th>Totale a doversi</th>
                <th></th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
                @php
                    $payments = $invoice->activePayments?->sum('amount') ?? 0;

                    $notes = $invoice->creditNotes?->sum('total') ?? 0;
                    $n[] = $notes;

                    $residue = $invoice->total - $payments - $notes;
                @endphp
                <tr class="record-row-first">
                    <td colspan="2">{{ $invoice->id }}</td>
                    <td colspan="14" class="description-cell">{{ trim($invoice->description) }}</td>
                </tr>
                <tr class="record-row-second">
                    <td colspan="2">{{ $invoice->docType?->name }}</td>
                    <td colspan="2">{{ $invoice->getNewInvoiceNumber() }}</td>
                    <td colspan="2">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                    <td colspan="2">{{ $invoice->client->denomination }}</td>
                    <td colspan="2">{{ $invoice->tax_type->getLabel() }}</td>
                    <td colspan="2">{{ $invoice->contract?->payment_type->getLabel() }}</td>
                    <td colspan="2">{{ euroFormat($invoice->total) }}</td>
                    <td colspan="2">{{ $invoice->sdi_status->getLabel() }}</td>
                </tr>
                <tr class="record-row-third">
                    <th>Competenza</th>
                    <td>{{ $invoice->accrual_year }}</td>
                    <th>Bilancio</th>
                    <td>{{ $invoice->budget_year }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <th>Pagamenti</th>
                    <td>{{ euroFormat($payments) }}</td>
                    <th>Nota</th>
                    <td>{{ euroFormat($notes) }}</td>
                    <th>Residuo</th>
                    <td>{{ euroFormat($residue) }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endforeach
            @php
                // dd($n);
            @endphp
        </tbody>
    </table>
</body>
</html>
