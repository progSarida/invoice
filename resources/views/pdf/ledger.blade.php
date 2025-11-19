<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Partitario</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 7px;
            margin: 20px;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 15px;
            margin: 0;
        }
        .header p {
            margin: 5px 0;
            font-size: 10px;
        }
        .table-container {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 7px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            margin-top: 10px;
            font-weight: bold;
        }
        .totals p {
            margin: 5px 0;
        }
        .no-border {
            border: none !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Partitario</h1>
            <p>{{ $company->name }}</p>
            @if (!empty($filters['from_date']) || !empty($filters['to_date']))
                <p>Da data: {{ $filters['from_date'] ? \Carbon\Carbon::parse($filters['from_date'])->format('d/m/Y') : 'N/D' }} - A data: {{ $filters['to_date'] ? \Carbon\Carbon::parse($filters['to_date'])->format('d/m/Y') : 'N/D' }}</p>
            @endif
            @if (!empty($filters['client_id']) && !empty($data[0]['cliente']))
                <p>Cliente: {{ $data[0]['cliente']['nome'] }}</p>
                <p>Partita iva: {{ $data[0]['cliente']['pi'] }} - Codice fiscale: {{ $data[0]['cliente']['cf'] }}</p>
            @endif
            <!-- @if ($filters['prec_residue'] && isset($residue))
                <p>Residuo Precedente: € {{ number_format($residue, 2, ',', '.') }}</p>
            @endif -->
        </div>

        <!-- Table -->
        <div class="table-container">
            <table>
                <tr class="no-border">
                    <td colspan="6" class="text-right">Residuo precedente:</td>
                    <td class="text-right no-border">€ {{ $filters['prec_residue'] ? number_format($residue, 2, ',', '.') : 0 }}</td>
                </tr>
                <thead>
                    <tr>
                        <th>Data Reg.</th>
                        {{-- <th>Cliente</th> --}}
                        <th>N. Doc.</th>
                        <th>Data Doc.</th>
                        <th>Descrizione</th>
                        <th class="text-right">Dare</th>
                        <th class="text-right">Avere</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalDare = 0;
                        $totalAvere = 0;
                    @endphp
                    @foreach ($data as $item)
                        <tr>
                            <td>{{ $item['reg'] }}</td>
                            {{-- <td>{{ $item['cliente'] }}</td> --}}
                            <td>{{ $item['num_doc'] }}</td>
                            <td>{{ $item['data_doc'] }}</td>
                            <td>{!! $item['desc'] !!}</td>
                            <td class="text-right">{{ $item['dare'] == 0 ? '' : '€ ' . number_format($item['dare'], 2, ',', '.') }}</td>
                            <td class="text-right">{{ $item['avere'] == 0 ? '' : '€ ' . number_format($item['avere'], 2, ',', '.') }}</td>
                            <td class="text-right">€ {{ number_format($item['saldo'], 2, ',', '.') }}</td>
                        </tr>
                        @php
                            $totalDare += $item['dare'];
                            $totalAvere += $item['avere'];
                        @endphp
                    @endforeach
                    <tr>
                        <td colspan="4" class="text-right">TOTALI</td>
                        <td class="text-right"> € {{ number_format($totalDare, 2, ',', '.') }}</td>
                        <td class="text-right"> € {{ number_format($totalAvere, 2, ',', '.') }}</td>
                        <td class="text-right"> € {{ number_format($data[count($data) - 1]['saldo'] ?? 0, 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        {{-- <div class="totals">
            <p>Totale Dare: € {{ number_format($totalDare, 2, ',', '.') }}</p>
            <p>Totale Avere: € {{ number_format($totalAvere, 2, ',', '.') }}</p>
            <p>Saldo Finale: € {{ number_format($data[count($data) - 1]['saldo'] ?? 0, 2, ',', '.') }}</p>
        </div> --}}
    </div>
</body>
</html>
