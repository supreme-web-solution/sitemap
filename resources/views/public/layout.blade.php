@php($brand = $team->brand())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ $brand['name'] }}</title>
    <style>
        :root{--brand:{{ preg_match('/^#[0-9a-fA-F]{6}$/', $brand['color']) ? $brand['color'] : '#18181b' }}}
        *{box-sizing:border-box}
        body{margin:0;background:#f4f4f5;color:#18181b;font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
        .wrap{max-width:720px;margin:0 auto;padding:24px 16px 64px}
        .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
        .brand img{max-height:40px;max-width:160px}
        .brand strong{font-size:18px}
        .brand-bar{height:4px;border-radius:4px;background:var(--brand);margin-bottom:20px}
        .card{background:#fff;border:1px solid #e4e4e7;border-radius:14px;padding:24px;margin-bottom:16px}
        .muted{color:#71717a}
        h1{font-size:22px;margin:0 0 4px}
        h2{font-size:16px;margin:0 0 12px}
        a{color:var(--brand)}
        table{width:100%;border-collapse:collapse;margin:8px 0}
        th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#71717a;text-align:left;font-weight:600}
        th,td{padding:10px 0;border-bottom:1px solid #f4f4f5;vertical-align:top}
        .num{text-align:right;white-space:nowrap}
        .badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f4f4f5}
        .badge.ok{background:#dcfce7;color:#166534}
        .badge.warn{background:#fef9c3;color:#854d0e}
        .badge.bad{background:#fee2e2;color:#991b1b}
        .notice{border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:14px}
        .notice.ok{background:#dcfce7;color:#166534}
        .notice.warn{background:#fef9c3;color:#854d0e}
        .btn,button{display:inline-block;padding:11px 18px;border:0;border-radius:10px;font:600 15px system-ui;cursor:pointer;background:var(--brand);color:#fff;text-decoration:none}
        button.alt,.btn.alt{background:#e4e4e7;color:#18181b}
        input[type=text],textarea{width:100%;padding:10px 12px;border:1px solid #d4d4d8;border-radius:8px;font:inherit}
        label{display:block;font-size:13px;margin:10px 0 4px}
        .row{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}
        .total{display:flex;justify-content:space-between;font-weight:700;padding-top:8px}
        .pre{white-space:pre-line}
        .foot{text-align:center;margin-top:20px;font-size:12px}
        .err{color:#991b1b;font-size:13px;margin-top:4px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        @if ($brand['logo'])
            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
        @endif
        <strong>{{ $brand['name'] }}</strong>
    </div>
    <div class="brand-bar"></div>

    @if (session('notice'))
        <div class="notice {{ session('notice_type', 'ok') }}">{{ session('notice') }}</div>
    @endif

    @yield('content')

    @if ($brand['email'])
        <div class="foot muted">Questions? <a href="mailto:{{ $brand['email'] }}">{{ $brand['email'] }}</a></div>
    @endif
    @include('public.powered-by', ['team' => $team])
</div>
</body>
</html>