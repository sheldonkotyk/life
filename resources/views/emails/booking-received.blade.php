<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f6f7f9; margin:0; padding:24px;">
    <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:12px; padding:32px;">
        <h1 style="font-size:20px; margin:0 0 16px;">
            @if ($booking->isAwaitingApproval())
                {{ $booking->guest_name }} asked for a time
            @else
                {{ $booking->guest_name }} booked a time
            @endif
        </h1>

        <div style="margin:0 0 24px; padding:16px; background:#f6f7f9; border-radius:8px;">
            <div style="font-weight:600;">{{ $booking->guest_title ?: $bookingPage->title }}</div>
            <div style="margin-top:4px; color:#374151;">
                {{ $booking->starts_at->setTimezone($bookingPage->timezone)->format('l, F j, Y · g:i A') }}
                · {{ $bookingPage->duration_minutes }} minutes
            </div>
            <div style="margin-top:4px; font-size:13px; color:#6b7280;">{{ $bookingPage->timezone }}</div>
            <div style="margin-top:12px; font-size:14px; color:#374151;">
                {{ $booking->guest_name }} · {{ $booking->guest_email }}
            </div>
            @if ($booking->notes)
                <div style="margin-top:8px; font-size:14px; color:#374151;">{{ $booking->notes }}</div>
            @endif
        </div>

        @if ($booking->isAwaitingApproval())
            <p style="font-size:14px; color:#374151;">The time is held on your calendar as tentative until you answer.</p>
            <p style="margin:16px 0;">
                <a href="{{ $booking->acceptUrl() }}" style="display:inline-block; background:#047857; color:#fff; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:600;">Accept</a>
                <a href="{{ $booking->declineUrl() }}" style="display:inline-block; margin-left:8px; background:#f3f4f6; color:#111827; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:600;">Decline</a>
            </p>
            <p style="font-size:14px;"><a href="{{ $booking->rescheduleUrl() }}" style="color:#374151;">Suggest another time</a></p>
        @else
            <p style="font-size:14px; color:#374151;">It's on your calendar and {{ $booking->guest_name }} has the invitation.</p>
            <p style="margin:16px 0; font-size:14px;">
                <a href="{{ $booking->rescheduleUrl() }}" style="color:#374151;">Move it</a>
                &nbsp;·&nbsp;
                <a href="{{ $booking->cancelUrl() }}" style="color:#374151;">Cancel it</a>
            </p>
        @endif
    </div>
</body>
</html>
