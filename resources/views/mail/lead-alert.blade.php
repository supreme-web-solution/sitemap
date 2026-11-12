@php
    $accent = ($brand['color'] ?? null) ?: '#18181b';
    $brandName = $brand['name'] ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;color:#18181b;font-family:Georgia, 'Times New Roman', serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;">
                    <tr>
                        <td style="padding:32px 32px 8px;font-family:Arial, Helvetica, sans-serif;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;color:#71717a;">
                            {{ $brandName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;font-size:28px;line-height:1.3;color:#18181b;">
                            {{ $title }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            {{ $intro }}
                        </td>
                    </tr>
                    @if (! empty($details))
                        <tr>
                            <td style="padding:20px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fafafa;border:1px solid #e4e4e7;border-radius:12px;">
                                    @foreach ($details as $label => $value)
                                        <tr>
                                            <td style="padding:{{ $loop->first ? '16px' : '0' }} 16px {{ $loop->last && empty($messageBody) ? '16px' : '8px' }} 16px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.5;color:#52525b;">
                                                <span style="display:block;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#71717a;margin-bottom:4px;">{{ $label }}</span>
                                                <span style="color:#18181b;font-weight:600;">{{ $value }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if (filled($messageBody ?? null))
                                        <tr>
                                            <td style="padding:8px 16px 16px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#3f3f46;">
                                                <span style="display:block;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#71717a;margin-bottom:6px;">{{ __('Message') }}</span>
                                                <span style="display:block;white-space:pre-line;color:#18181b;">{{ $messageBody }}</span>
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    @elseif (filled($messageBody ?? null))
                        <tr>
                            <td style="padding:20px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fafafa;border:1px solid #e4e4e7;border-radius:12px;">
                                    <tr>
                                        <td style="padding:16px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#18181b;white-space:pre-line;">
                                            {{ $messageBody }}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:999px;background:{{ $accent }};">
                                        <a href="{{ $url }}" style="display:inline-block;padding:14px 22px;font-family:Arial, Helvetica, sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                                            {{ $cta }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#71717a;">
                            {{ $footer ?? __('Open the lead in :app to follow up.', ['app' => config('app.name')]) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
