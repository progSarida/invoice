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
    <h2 style="text-align: center"><u>Elenco Polizze</u></h2>
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif
            @php
                $fieldTranslations = [
                    'insurance' => 'Assicurazione',
                    'tax_types' => 'Entrate',
                    'bail_status' => 'Stato',
                    'expiration_status' => 'Stato scadenza',
                    'not_paid' => 'Non pagati',
                    'not_receipt' => 'Senza allegato quietanza',
                    'active_at_date' => 'Attive al',
                ];

                $fieldValues = [
                    'tax_types' => [
                        'cds' => 'Codice della Strada',
                        'ici' => 'Imposta Comunale sugli Immobili',
                        'imu' => 'Imposta Municipale Unica',
                        'libero' => 'Libera',
                        'parcheggio' => 'Parcheggio',
                        'pubblicita' => "Imposta sulla Pubblicita'",
                        'tari' => 'Tassa sui Rifiuti',
                        'tep' => 'TEP',
                        'tosap' => 'Tassa per l\'Occupazione del Suolo Pubblico',
                    ],
                    'bail_status' => [
                        'payed' => 'Pagata',
                        'not_payed' => 'Non pagata',
                        'released' => 'Svincolata',
                        'not_released' => 'Non svincolata',
                    ],
                    'expiration_status' => [
                        'expired' => 'Scaduti',
                        'expired_not_paid' => 'Scaduti e non pagati',
                        'expired_not_released' => 'Scaduti e non svincolati',
                    ],
                ];
            @endphp
            @foreach($filters as $field => $data)
                @if($field == 'insurance' && !empty($data['value']))
                    @php
                        $insurance = \App\Models\Insurance::find($data['value']);
                    @endphp
                    <li>{{ $fieldTranslations[$field] ?? ucfirst($field) }}: {{ $insurance->name ?? 'N/A' }}</li>

                @elseif($field == 'tax_types' && !empty($data['values']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        @php
                            $val = [];
                            foreach($data['values'] as $el) {
                                $val[] = $fieldValues['tax_types'][$el] ?? \App\Enums\TaxType::from($el)->getLabel();
                            }
                        @endphp
                        {{ implode(', ', $val) }}
                    </li>

                @elseif($field == 'bail_status' && !empty($data['value']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        {{ $fieldValues['bail_status'][$data['value']] ?? \App\Enums\BailStatus::from($data['value'])->getLabel() }}
                    </li>

                @elseif($field == 'expiration_status' && !empty($data['value']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        {{ $fieldValues['expiration_status'][$data['value']] ?? $data['value'] }}
                    </li>

                @elseif($field == 'not_paid' && !empty($data['not_paid']))
                    <li>{{ $fieldTranslations[$field] ?? ucfirst($field) }}: Sì</li>

                @elseif($field == 'not_receipt' && !empty($data['not_receipt']))
                    <li>{{ $fieldTranslations[$field] ?? ucfirst($field) }}: Sì</li>

                @elseif($field == 'active_at_date' && !empty($data['selected_date']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        {{ \Carbon\Carbon::parse($data['selected_date'])->format('d/m/Y') }}
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>CIG</th>
                <th>Entrata</th>
                <th>Compagnia</th>
                <th>Tipo</th>
                <th>Numero</th>
                <th>Premio</th>
                <th>Inizio</th>
                <th>Scadenza</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bails as $bail)
                <tr>
                    <td>{{ $bail->contract->client->denomination }}</td>
                    <td>{{ $bail->cig_code }}</td>
                    <td>{{ !empty($bail->tax_types) ? implode(', ', $bail->tax_types) : 'N/A' }}</td>
                    <td>{{ $bail->insurance->name }}</td>
                    <td>{{ $bail->bail_type?->getLabel() }}</td>
                    <td>{{ $bail->bill_number }}</td>
                    {{-- @if ($filters["active_at_date"]["selected_date"]) --}}
                    @php
                        $detail = $bail->selectedDetail($filters["active_at_date"]["selected_date"])
                    @endphp
                        <td>{{ $detail->premium }}</td>
                        <td>{{ \Carbon\Carbon::parse($detail->bill_start)->format('d/m/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($detail->bill_deadline)->format('d/m/Y') }}</td>
                    {{-- @else
                        <td>{{ $bail->lastDetail->premium }}</td>
                        <td>{{ $bail->lastDetail->bill_start }}</td>
                        <td>{{ $bail->lastDetail->bill_deadline }}</td>
                    @endif --}}
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
