<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f6f7f9; margin:0; padding:24px;">
    <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:12px; padding:32px;">
        <h1 style="font-size:20px; margin:0 0 16px;">{{ $booking->guest_name }} declined</h1>
        <p>The meeting has been removed from your calendar and the time is bookable again.</p>

        <div style="margin:24px 0; padding:16px; background:#f6f7f9; border-radius:8px;">
            <div style="font-weight:600;">{{ $booking->guest_title ?: $bookingPage->title }}</div>
            <div style="margin-top:4px; color:#374151;">
                {{ $booking->starts_at->setTimezone($bookingPage->timezone)->format('l, F j, Y · g:i A') }}
                · {{ $bookingPage->duration_minutes }} minutes
            </div>
            <div style="margin-top:12px; font-size:14px; color:#374151;">{{ $booking->guest_email }}</div>
        </div>
    </div>
</body>
</html>
