<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        h2 {
            text-align: center;
            margin-bottom: 10px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            border: 2px solid black;
            margin-top: 10px;
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
            background-color: #f0f0f0;
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

        ul {
            margin-top: 0;
            margin-bottom: 10px;
        }
    </style>
</head>
@php
    // dd($filters);
@endphp
<body>
    <h2><u>Elenco Allegati</u></h2>

    {{-- Sezione filtri --}}
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif
            @php
                $fieldTranslations = [
                    'client_id' => 'Cliente',
                    'attachment_type' => 'Tipo allegato',
                    'interval_date' => 'Intervallo di date',
                ];
            @endphp
            @foreach($filters as $field => $data)
                @if(!empty($data['values']) || !empty($data['start_date']) || !empty($data['end_date']))
                    <li>
                        {{ $fieldTranslations[$field] ?? ucfirst($field) }}:
                        @if(!empty($data['values']))
                            @php
                                $types = [];
                                foreach($data['values'] as $value)
                                    $types[] = \App\Enums\AttachmentType::tryFrom($value)->getLabel();
                            @endphp
                            {{ implode(', ', $types) }}
                            {{-- {{ implode(', ', $data['values']) }} --}}
                        @elseif(!empty($data['start_date']) || !empty($data['end_date']))
                            @php
                                $start = $data['start_date'] ? \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y') : null;
                                $end = $data['end_date'] ? \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y') : null;
                            @endphp
                            @if($start && $end)
                                Dal {{ $start }} al {{ $end }}
                            @elseif($start)
                                Dal {{ $start }}
                            @elseif($end)
                                Al {{ $end }}
                            @endif
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    {{-- Tabella principale --}}
    <table>
        <thead>
            <tr>
                <th>Tipo allegato</th>
                <th>Data caricamento</th>
                <th>Cliente</th>
                <th>Contratto</th>
                <th>Data</th>
                <th>Nome file</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attachments as $attachment)
                @php
                    $contract = \App\Models\NewContract::find($attachment->contract_id);
                @endphp
                <tr>
                    @php
                        $type = $attachment->attachment_type instanceof \App\Enums\AttachmentType
                            ? $attachment->attachment_type
                            : \App\Enums\AttachmentType::tryFrom($attachment->attachment_type);
                    @endphp
                    <td>{{ $type?->getLabel() ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($attachment->attachment_upload_date)->format('d/m/Y') }}</td>
                    <td>{{ $attachment->client?->denomination }}</td>
                    <td>
                        @if($contract)
                            {{ "{$contract->office_name} ({$contract->office_code}) - CIG: {$contract->cig_code}" }}
                        @endif
                    </td>
                    <td>{{ $attachment->attachment_date ? \Carbon\Carbon::parse($attachment->attachment_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ basename($attachment->attachment_filename) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
