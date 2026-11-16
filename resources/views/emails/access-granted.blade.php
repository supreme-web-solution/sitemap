<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access activated</title>
</head>
<body style="font-family: Arial, sans-serif; background:#0f1117; color:#e2e8f0; margin:0; padding:24px;">
    <div style="max-width:640px; margin:0 auto; background:#1a1f2e; border:1px solid #2d3748; border-radius:12px; overflow:hidden;">
        <div style="padding:20px 24px; border-bottom:1px solid #2d3748;">
            <h1 style="margin:0; font-size:20px; color:#fff;">Your {{ $accessLabel }} access is active</h1>
            <p style="margin:8px 0 0; font-size:13px; color:#94a3b8;">
                Thanks for your purchase — it has been added to your existing {{ config('app.name') }} account.
            </p>
        </div>
        <div style="padding:20px 24px;">
            <p style="margin:0 0 16px; font-size:13px;"><strong>Email:</strong> {{ $user->email }}</p>
            <p style="margin:0 0 16px;">
                <a href="{{ rtrim((string) config('app.url'), '/') }}/login" style="display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; background:#40E0D0; color:#061019; font-weight:700; font-size:13px;">
                    Sign In
                </a>
            </p>
            <p style="margin:0; font-size:13px; color:#94a3b8;">
                Forgot your password?
                <a href="{{ rtrim((string) config('app.url'), '/') }}/forgot-password" style="color:#40E0D0;">Reset it here</a>.
            </p>
        </div>
    </div>
</body>
</html>
