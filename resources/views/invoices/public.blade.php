@php
    use App\Enums\InvoiceStatus;
    use App\Models\Invoice;
    $isPaid = $invoice->status === InvoiceStatus::Paid;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice {{ $invoice->number }} · {{ $invoice->team->name }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#f4f4f5;color:#18181b;font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
        .wrap{max-width:640px;margin:0 auto;padding:32px 16px 64px}
        .card{background:#fff;border:1px solid #e4e4e7;border-radius:14px;padding:28px}
        .muted{color:#71717a}
        h1{font-size:22px;margin:0 0 4px}
        .head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
        .badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f4f4f5}
        .badge.paid{background:#dcfce7;color:#166534}
        .badge.void{background:#fee2e2;color:#991b1b}
        table{width:100%;border-collapse:collapse;margin:24px 0 8px}
        th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#71717a;text-align:left;font-weight:600}
        th,td{padding:10px 0;border-bottom:1px solid #f4f4f5}
        .num{text-align:right;white-space:nowrap}
        .total{display:flex;justify-content:space-between;font-size:20px;font-weight:700;padding-top:12px}
        .notice{border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:14px}
        .notice.ok{background:#dcfce7;color:#166534}
        .notice.warn{background:#fef9c3;color:#854d0e}
        .notice.bad{background:#fee2e2;color:#991b1b}
        .pay{display:grid;gap:10px;margin-top:24px}
        button{width:100%;padding:13px 16px;border:0;border-radius:10px;font:600 15px system-ui;cursor:pointer;background:#18181b;color:#fff}
        button.alt{background:#0070ba}
        button:hover{opacity:.9}
        .notes{margin-top:20px;white-space:pre-line;font-size:14px}
        .foot{text-align:center;margin-top:20px;font-size:12px}
    </style>
</head>
<body>
<div class="wrap">
    @if ($notice === 'paid' || $isPaid)
        <div class="notice ok">Payment received. Thank you!</div>
    @elseif ($notice === 'pending')
        <div class="notice warn">We have not received confirmation of your payment yet. If you just paid, this page will update shortly. You can refresh in a minute.</div>
    @elseif ($notice === 'cancelled')
        <div class="notice warn">Payment was cancelled. You have not been charged.</div>
    @elseif ($notice === 'error')
        <div class="notice bad">We could not start the payment. Please try again or contact {{ $invoice->team->name }}.</div>
    @endif

    <div class="card">
        <div class="head">
            <div>
                <h1>Invoice {{ $invoice->number }}</h1>
                <div class="muted">From {{ $invoice->team->name }}</div>
                <div class="muted">To {{ $invoice->lead->name }}</div>
            </div>
            <div style="text-align:right">
                <span class="badge {{ $isPaid ? 'paid' : ($invoice->status === InvoiceStatus::Void ? 'void' : '') }}">{{ $invoice->status->label() }}</span>
                @if ($invoice->due_on && ! $isPaid)
                    <div class="muted" style="margin-top:6px">Due {{ $invoice->due_on->toFormattedDateString() }}</div>
                @endif
                @if ($isPaid && $invoice->paid_at)
                    <div class="muted" style="margin-top:6px">Paid {{ $invoice->paid_at->toFormattedDateString() }}</div>
                @endif
            </div>
        </div>

        <table>
            <thead>
            <tr><th>Description</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ Invoice::formatCents($item->unit_cents) }}</td>
                    <td class="num">{{ Invoice::formatCents($item->totalCents()) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="total"><span>Total (USD)</span><span>{{ Invoice::formatCents($invoice->total_cents) }}</span></div>

        @if ($invoice->notes)
            <div class="notes muted">{{ $invoice->notes }}</div>
        @endif

        @if ($invoice->status === InvoiceStatus::Void)
            <div class="notice bad" style="margin-top:20px;margin-bottom:0">This invoice has been cancelled.</div>
        @elseif (count($providers) > 0)
            <div class="pay">
                @foreach ($providers as $provider)
                    <form method="POST" action="{{ route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => $provider->value]) }}">
                        @csrf
                        <button type="submit" class="{{ $provider->value === 'paypal' ? 'alt' : '' }}">{{ $provider->payLabel() }}</button>
                    </form>
                @endforeach
            </div>
        @elseif (! $isPaid)
            <div class="notice warn" style="margin-top:20px;margin-bottom:0">Online payment is not available for this invoice. Please contact {{ $invoice->team->name }} to arrange payment.</div>
        @endif
    </div>
    <div class="foot muted">Payments are processed securely by the provider you choose. Card details never touch this site.</div>
    @include('public.powered-by', ['team' => $invoice->team])
</div>
</body>
</html>