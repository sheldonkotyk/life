<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f6f7f9; margin:0; padding:24px;">
    <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:12px; padding:32px;">
        <h1 style="font-size:20px; margin:0 0 16px;">{{ $bookingPage->user->name }} couldn't take that time</h1>
        <p>The hold has been removed from your calendar. You're welcome to pick another time that works.</p>

        <div style="margin:24px 0; padding:16px; background:#f6f7f9; border-radius:8px;">
            <div style="font-weight:600;">{{ $booking->guest_title ?: $bookingPage->title }}</div>
            <div style="margin-top:4px; color:#374151;">
                {{ $booking->starts_at->setTimezone($booking->guest_timezone)->format('l, F j, Y · g:i A') }}
            </div>
        </div>

        <p style="margin:16px 0;">
            <a href="{{ route('booking.show', $bookingPage) }}" style="display:inline-block; background:#111827; color:#fff; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:600;">Choose another time</a>
        </p>
    </div>
</body>
</html>
