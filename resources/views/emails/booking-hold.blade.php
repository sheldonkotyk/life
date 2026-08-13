<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f6f7f9; margin:0; padding:24px;">
    <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:12px; padding:32px;">
        <h1 style="font-size:20px; margin:0 0 16px;">
            {{ $moved ? $bookingPage->user->name.' suggested a different time' : 'Your request is with '.$bookingPage->user->name }}
        </h1>
        <p>{{ $moved ? 'The hold on your calendar has moved to the time below, and is still waiting to be confirmed.' : "They're holding this time while they confirm. The attached invitation puts it on your calendar as tentative so nothing else takes the slot." }}</p>

        <div style="margin:24px 0; padding:16px; background:#f6f7f9; border-radius:8px;">
            <div style="font-weight:600;">{{ $booking->guest_title ?: $bookingPage->title }}</div>
            <div style="margin-top:4px; color:#374151;">
                {{ $booking->starts_at->setTimezone($booking->guest_timezone)->format('l, F j, Y · g:i A') }}
                · {{ $bookingPage->duration_minutes }} minutes
            </div>
            <div style="margin-top:4px; font-size:13px; color:#6b7280;">{{ $booking->guest_timezone }}</div>
        </div>

        <p style="font-size:14px; color:#374151;">You'll get a confirmed invitation once they accept. Need to change it?</p>
        <p style="margin:16px 0;">
            <a href="{{ $booking->rescheduleUrl() }}" style="display:inline-block; background:#111827; color:#fff; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:600;">Pick a different time</a>
            <a href="{{ $booking->cancelUrl() }}" style="display:inline-block; margin-left:8px; background:#f3f4f6; color:#111827; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:600;">Cancel the request</a>
        </p>
    </div>
</body>
</html>
