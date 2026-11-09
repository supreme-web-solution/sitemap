@php
    $brand = $team->brand();
    $accent = $brand['color'] ?: '#18181b';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Weekly snapshot') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;color:#18181b;font-family:Georgia, 'Times New Roman', serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;">
                    <tr>
                        <td style="padding:32px 32px 8px;font-family:Arial, Helvetica, sans-serif;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;color:#71717a;">
                            {{ config('app.name') }} · {{ $team->name }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;font-size:28px;line-height:1.3;color:#18181b;">
                            {{ __('Your weekly snapshot') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            {{ __('Here is how :team looked over the last seven days.', ['team' => $team->name]) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:8px 0;">
                                <tr>
                                    <td width="33%" valign="top" style="background:#fafafa;border:1px solid #e4e4e7;border-radius:12px;padding:16px;">
                                        <div style="font-family:Arial, Helvetica, sans-serif;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#71717a;">{{ __('Open leads') }}</div>
                                        <div style="font-family:Arial, Helvetica, sans-serif;font-size:28px;font-weight:700;color:#18181b;padding-top:6px;">{{ $stats['openLeads'] }}</div>
                                    </td>
                                    <td width="33%" valign="top" style="background:#fafafa;border:1px solid #e4e4e7;border-radius:12px;padding:16px;">
                                        <div style="font-family:Arial, Helvetica, sans-serif;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#71717a;">{{ __('Won this week') }}</div>
                                        <div style="font-family:Arial, Helvetica, sans-serif;font-size:28px;font-weight:700;color:#18181b;padding-top:6px;">{{ $stats['wonThisWeek'] }}</div>
                                    </td>
                                    <td width="33%" valign="top" style="background:#fafafa;border:1px solid #e4e4e7;border-radius:12px;padding:16px;">
                                        <div style="font-family:Arial, Helvetica, sans-serif;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#71717a;">{{ __('Outstanding') }}</div>
                                        <div style="font-family:Arial, Helvetica, sans-serif;font-size:20px;font-weight:700;color:#18181b;padding-top:10px;">{{ $stats['outstanding'] }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:999px;background:{{ $accent }};">
                                        <a href="{{ $stats['dashboardUrl'] }}" style="display:inline-block;padding:14px 22px;font-family:Arial, Helvetica, sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                                            {{ __('Open the dashboard') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#71717a;">
                            {{ __('Want the deeper breakdown?') }}
                            <a href="{{ $stats['reportsUrl'] }}" style="color:#18181b;font-weight:600;">{{ __('View reports') }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
