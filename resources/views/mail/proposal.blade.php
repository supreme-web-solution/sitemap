@php
    $brand = $proposal->team->brand();
    $client = $proposal->lead->name;
    $url = $proposal->publicUrl();
    $accent = $brand['color'] ?: '#18181b';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $proposal->title }}</title>
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
                            {{ $isPreview ?? false ? 'Preview: your proposal email' : 'Your proposal is ready' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            Hi {{ $client }},
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:16px;line-height:1.6;color:#3f3f46;">
                            {{ $senderName }} at <strong style="color:#18181b;">{{ $brand['name'] }}</strong> put together
                            <strong style="color:#18181b;">{{ $proposal->title }}</strong> for you.
                            @if ($proposal->valid_until)
                                It stays open until {{ $proposal->valid_until->toFormattedDateString() }}.
                            @endif
                        </td>
                    </tr>
                    @if (filled($proposal->intro))
                        <tr>
                            <td style="padding:16px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:15px;line-height:1.65;color:#52525b;white-space:pre-line;">{{ $proposal->intro }}</td>
                        </tr>
                    @endif
                    @if ($pitchVideo ?? null)
                        <tr>
                            <td style="padding:24px 32px 0;font-family:Arial, Helvetica, sans-serif;">
                                <a href="{{ $pitchVideo->watchUrl() }}" style="text-decoration:none;color:#18181b;">
                                    @if ($pitchVideo->presenter_thumbnail_url)
                                        <img src="{{ $pitchVideo->presenter_thumbnail_url }}" alt="Watch the video" width="496" style="display:block;width:100%;max-width:496px;height:auto;border-radius:12px;border:1px solid #e4e4e7;">
                                    @endif
                                    <span style="display:block;padding-top:10px;font-size:15px;font-weight:700;color:{{ $accent }};">&#9654; Watch a short video about your website</span>
                                </a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:999px;background:{{ $accent }};">
                                        <a href="{{ $url }}" style="display:inline-block;padding:14px 22px;font-family:Arial, Helvetica, sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                                            View the proposal
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#52525b;">
                            Review the offer, ask questions, and accept when you’re ready.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 32px;font-family:Arial, Helvetica, sans-serif;font-size:14px;line-height:1.6;color:#71717a;">
                            Questions? Reply to this email and it goes to {{ $senderName }} at {{ $brand['name'] }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
