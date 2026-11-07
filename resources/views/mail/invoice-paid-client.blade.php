@php
    $brand = $invoice->team->brand();
    $accent = $brand['color'] ?: '#18181b';
    $client = $invoice->lead->name;
    $amount = \App\Models\Invoice::formatCents($invoice->total_cents);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment received</title>
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
                            @if ($downloadUrl)
                                Your website is ready
                            @else
                                Payment received
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            Hi {{ $client }},
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            Thanks — we received your payment of <strong style="color:#18181b;">{{ $amount }}</strong>
                            for invoice <strong style="color:#18181b;">{{ $invoice->number }}</strong>.
                        </td>
                    </tr>
                    @if (filled($invoice->notes))
                        <tr>
                            <td style="padding:12px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#52525b;">
                                {{ $invoice->notes }}
                            </td>
                        </tr>
                    @endif
                    @if ($downloadUrl)
                        <tr>
                            <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                                The website for <strong style="color:#18181b;">{{ $client }}</strong> is ready for you.
                                Download the ZIP below — one folder per page, plus robots.txt. No login needed.
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:28px 32px 8px;">
                                <table role="presentation" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="border-radius:999px;background:{{ $accent }};">
                                            <a href="{{ $downloadUrl }}" style="display:inline-block;padding:14px 22px;font-family:Arial, Helvetica, sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                                                Download your website
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:8px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#52525b;">
                                This private link works until {{ $expiresOn }}.
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:20px 32px 32px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#71717a;">
                            Questions? Reply to this email and it goes to {{ $brand['name'] }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
