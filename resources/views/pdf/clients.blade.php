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
    <h2 style="text-align: center"><u>Elenco Clienti</u></h2>
    @if(!empty($filters))
        <p><strong>Filtri applicati:</strong></p>
        <ul>
            @if($search)
                <li>
                    Ricerca:
                    {{ $search }}
                </li>
            @endif
            @foreach($filters as $field => $data)
                @if(!empty($data['values']))
                    <li>
                        {{ ucfirst($field) == 'Type' ? 'Tipo' : ''}}:
                        {{ implode(', ', array_map(fn($value) => \App\Enums\ClientType::tryFrom($value)?->getLabel(), $data['values'])) }}
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Denominazione</th>
                <th>Email</th>
                <th>Codice univoco</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clients as $client)
                <tr>
                    <td>{{ $client->type->getLabel() }}</td>
                    <td>{{ trim($client->denomination) }}</td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $client->ipa_code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
