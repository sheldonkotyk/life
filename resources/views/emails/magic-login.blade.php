<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f6f7f9; margin:0; padding:24px;">
    <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:12px; padding:32px;">
        <h1 style="font-size:20px; margin:0 0 16px;">✦ Sign in to Life</h1>
        <p>Use either method below to sign in. Both expire in {{ $minutes }} minutes and can only be used once.</p>

        <p style="margin:24px 0;">
            <a href="{{ $url }}" style="display:inline-block; background:#111827; color:#fff; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:600;">Sign in</a>
        </p>

        <p style="margin:24px 0; font-size:14px; color:#374151;">Or enter this code on the sign-in page:</p>
        <p style="margin:8px 0 24px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:28px; font-weight:700; letter-spacing:8px; color:#111827;">{{ $code }}</p>

        <p style="font-size:12px; color:#6b7280;">If you didn't request this, you can safely ignore the email.</p>
        <p style="font-size:12px; color:#6b7280; word-break:break-all;">Or paste this URL into your browser: {{ $url }}</p>
    </div>
</body>
</html>
