<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Protected preview</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: grid; place-items: center; margin: 0; }
        form { background: #1e293b; padding: 1.5rem; border-radius: 1rem; width: min(22rem, 90vw); display: grid; gap: .75rem; }
        h1 { margin: 0; font-size: 1.15rem; }
        p { margin: 0; color: #94a3b8; font-size: .9rem; }
        input, button { font: inherit; padding: .7rem .85rem; border-radius: .7rem; border: 1px solid #334155; }
        input { background: #0f172a; color: #fff; }
        button { background: #22c55e; color: #052e16; border: 0; font-weight: 700; cursor: pointer; }
        .error { color: #fda4af; font-size: .85rem; }
    </style>
</head>
<body>
    <form method="POST" action="{{ route('sites.preview.unlock', $site->slug) }}">
        @csrf
        <h1>This preview is private</h1>
        <p>Enter the password the agency sent you to view the website.</p>
        <input type="password" name="password" required maxlength="100" autofocus placeholder="Password">
        @error('password')
            <p class="error">{{ $message }}</p>
        @enderror
        <button type="submit">Unlock</button>
    </form>
</body>
</html>
