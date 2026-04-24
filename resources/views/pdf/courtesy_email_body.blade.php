<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fattura {{ $invoiceNumber }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .info-box {
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        strong {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Fattura {{ $invoiceNumber }}</h2>

        <p>Gentile <strong>{{ $clientName }}</strong>,</p>

        <div class="info-box">
            <p style="margin: 0;">
                In allegato trova la fattura:<br>
                <strong>N. {{ $invoiceNumber }}</strong><br>
                Data: <strong>{{ $invoiceDate }}</strong>
            </p>
        </div>

        <p>Il documento è disponibile in formato PDF in allegato a questa email.</p>

        <p>Per qualsiasi chiarimento o informazione rimaniamo a vostra completa disposizione.</p>

        <p style="margin-top: 30px;">
            Cordiali saluti,<br>
            <strong>{{ $invoice->company->name }}</strong>
        </p>

        @if($invoice->company->address)
        <p style="font-size: 13px; color: #555;">
            {{ $invoice->company->address }}
            @if($invoice->company->city)
                <br>{{ $invoice->company->city->zip_code }} {{ $invoice->company->city->name }} ({{ $invoice->company->city->province->code }})
            @endif
            @if($invoice->company->phone)
                <br>Tel: {{ $invoice->company->phone }}
            @endif
            @if($invoice->company->email)
                <br>Email: {{ $invoice->company->email }}
            @endif
        </p>
        @endif

        <div class="footer">
            <p style="margin: 0;">
                Questa è una email automatica. Si prega di non rispondere direttamente a questo messaggio.
            </p>
            @if($invoice->company->vat_number)
            <p style="margin: 5px 0 0 0;">
                P.IVA: {{ $invoice->company->vat_number }}
                @if($invoice->company->tax_number)
                    | C.F: {{ $invoice->company->tax_number }}
                @endif
            </p>
            @endif
        </div>
    </div>
</body>
</html>
