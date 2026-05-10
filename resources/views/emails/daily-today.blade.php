<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f6f7f9; margin:0; padding:24px;">
    <div style="max-width:520px; margin:0 auto; background:#fff; border-radius:12px; padding:32px;">
        <div style="text-align:center; padding-bottom:20px; margin-bottom:24px; border-bottom:1px solid #e5e7eb;">
            <div style="font-size:18px; font-weight:700; letter-spacing:0.3px; color:#111827;">✦ {{ $brand }}</div>
        </div>

        <h1 style="font-size:20px; margin:0 0 4px;">Good morning, {{ $user->name }}</h1>
        <p style="margin:0 0 24px; color:#6b7280; font-size:14px;">Here's your day — {{ $dateLabel }}.</p>

        @if (! $digest['has_content'])
            <p style="color:#374151;">Nothing on the schedule. Enjoy a quiet day.</p>
        @endif

        @if (count($digest['meals']) > 0)
            <h2 style="font-size:16px; margin:24px 0 8px;">Meals</h2>
            <ul style="padding-left:18px; margin:0; color:#111827;">
                @foreach ($digest['meals'] as $meal)
                    <li style="margin-bottom:6px;">
                        <strong>{{ $meal['slot'] }}</strong>
                        @if ($meal['time']) <span style="color:#6b7280;">· {{ $meal['time'] }}</span> @endif
                        — {{ $meal['name'] }}
                    </li>
                @endforeach
            </ul>
        @endif

        @if (count($digest['todos']) > 0)
            <h2 style="font-size:16px; margin:24px 0 8px;">To-do</h2>
            <ul style="padding-left:18px; margin:0; color:#111827;">
                @foreach ($digest['todos'] as $todo)
                    <li style="margin-bottom:6px;">
                        {{ $todo['title'] }}
                        @if ($todo['list']) <span style="color:#6b7280;">· {{ $todo['list'] }}</span> @endif
                        @if ($todo['assigned_to_me']) <span style="color:#2563eb;">· you</span> @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <p style="margin:32px 0 0;">
            <a href="{{ $todayUrl }}" style="display:inline-block; background:#111827; color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:600;">Open Today</a>
        </p>

        <p style="margin-top:32px; font-size:12px; color:#9ca3af;">
            You're receiving this because you set a daily digest time in your Life profile. Change or disable it under Profile → Notifications.
        </p>
    </div>
</body>
</html>
