@php
    $brand = $invoice->team->brand();
    $lead = $invoice->lead;
    $amount = \App\Models\Invoice::formatCents($invoice->total_cents);
    $via = $invoice->paid_via ? ucfirst($invoice->paid_via) : __('card');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number }} paid</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;color:#18181b;font-family:Georgia, 'Times New Roman', serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;">
                    <tr>
                        <td style="padding:32px 32px 8px;font-family:Arial, Helvetica, sans-serif;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;color:#71717a;">
                            {{ $brand['name'] }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;font-size:28px;line-height:1.3;color:#18181b;">
                            Payment received
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            <strong style="color:#18181b;">{{ $lead->name }}</strong> paid
                            <strong style="color:#18181b;">{{ $amount }}</strong> on invoice
                            <strong style="color:#18181b;">{{ $invoice->number }}</strong> via {{ $via }}.
                        </td>
                    </tr>
                    @if (filled($invoice->notes))
                        <tr>
                            <td style="padding:12px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#52525b;">
                                {{ $invoice->notes }}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:28px 32px 32px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#71717a;">
                            The client has been emailed a receipt@if ($lead->site && $lead->site->transferred_at) and their website download link@endif.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
