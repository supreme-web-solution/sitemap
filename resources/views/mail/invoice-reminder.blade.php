@php
    $brand = $invoice->team->brand();
    $accent = $brand['color'] ?: '#18181b';
    $amount = \App\Models\Invoice::formatCents($invoice->total_cents);
    $url = $invoice->payUrl();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reminder: {{ $invoice->number }}</title>
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
                            Friendly payment reminder
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            Hi {{ $invoice->lead->name }},
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            Invoice <strong style="color:#18181b;">{{ $invoice->number }}</strong> for
                            <strong style="color:#18181b;">{{ $amount }}</strong>
                            was due on {{ $invoice->due_on->toFormattedDateString() }} and has not been paid yet.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:999px;background:{{ $accent }};">
                                        <a href="{{ $url }}" style="display:inline-block;padding:14px 22px;font-family:Arial, Helvetica, sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                                            Pay invoice now
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#71717a;">
                            If you have already paid, you can ignore this message. Questions? Reply to this email for {{ $senderName }} at {{ $brand['name'] }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
