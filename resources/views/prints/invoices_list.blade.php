<table>
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Numero</th>
            <th>Created At</th>
        </tr>
    </thead>
    <tbody>
        @foreach($records as $record)
            <tr>
                <td>{{ $record->invoice_type->getLabel() }}</td>
                <td>{{ $record->getInvoiceNumber() }}</td>
                <td>{{ $record->date }}</td>
            </tr>
        @endforeach
    </tbody>
</table>