<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; margin: 3rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
        th, td { text-align: left; padding: 0.5rem 0; border-bottom: 1px solid #e5e5e5; }
        .total { font-weight: bold; font-size: 1.1rem; }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <p class="muted">Issued {{ $invoice->issued_at->toFormattedDateString() }}</p>

    <table>
        <tr>
            <th>Billed to</th>
            <td>{{ $invoice->tenant->name }}</td>
        </tr>
        <tr>
            <th>Plan</th>
            <td>{{ $invoice->subscription->plan->name }}</td>
        </tr>
        <tr>
            <th>Payment method</th>
            <td>{{ ucfirst($invoice->payment->payment_method->value) }}</td>
        </tr>
        <tr class="total">
            <th>Total</th>
            <td>{{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}</td>
        </tr>
    </table>
</body>
</html>
