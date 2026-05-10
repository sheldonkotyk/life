<?php

use App\Models\User;
use App\Notifications\DailyTodayDigest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('sends to a user when their local time matches the preference', function () {
    Notification::fake();

    // Pin "now" to a known UTC moment.
    CarbonImmutable::setTestNow('2026-05-10 13:02:00'); // 09:02 in America/Toronto

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
        'notification_preferences' => ['site' => true, 'email' => true, 'push' => false],
    ]);

    $this->artisan('notifications:send-daily-digest')->assertSuccessful();

    Notification::assertSentTo($user, DailyTodayDigest::class);
    expect((string) $user->fresh()->daily_today_email_last_sent_on)->toContain('2026-05-10');
});

it('does not send before the preferred time', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 12:55:00'); // 08:55 Toronto

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
    ]);

    $this->artisan('notifications:send-daily-digest')->assertSuccessful();

    Notification::assertNothingSentTo($user);
});

it('does not send twice in the same local day', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 13:02:00');

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
    ]);

    $this->artisan('notifications:send-daily-digest');
    Notification::assertSentToTimes($user, DailyTodayDigest::class, 1);

    // Five minutes later, still the same local day.
    CarbonImmutable::setTestNow('2026-05-10 13:07:00');
    $this->artisan('notifications:send-daily-digest');

    Notification::assertSentToTimes($user, DailyTodayDigest::class, 1);
});

it('respects the email channel preference', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 13:02:00');

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
        'notification_preferences' => ['site' => true, 'email' => false, 'push' => false],
    ]);

    $this->artisan('notifications:send-daily-digest');

    Notification::assertNothingSentTo($user);
});

it('respects different timezones for separate users', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 13:02:00'); // 09:02 Toronto, 14:02 London

    $toronto = loginUser();
    $toronto->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
    ]);

    $london = User::create([
        'household_id' => $toronto->household_id,
        'name' => 'London User',
        'email' => 'b@example.test',
        'timezone' => 'Europe/London',
        'daily_today_email_at' => '14:00',
    ]);

    $this->artisan('notifications:send-daily-digest');

    Notification::assertSentTo($toronto, DailyTodayDigest::class);
    Notification::assertSentTo($london, DailyTodayDigest::class);
});
