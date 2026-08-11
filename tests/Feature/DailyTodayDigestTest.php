<?php

use App\Models\User;
use App\Notifications\DailyTodayDigest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
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
        'daily_today_email_enabled' => true,
        'notification_preferences' => ['site' => true, 'email' => true, 'push' => false],
    ]);

    $this->artisan('notifications:send-daily-digest')->assertSuccessful();

    Notification::assertSentTo($user, DailyTodayDigest::class);
    expect((string) $user->fresh()->daily_today_email_last_sent_on)->toContain('2026-05-10');
});

it('retries with a delay so a locked database does not drop the digest', function () {
    $notification = new DailyTodayDigest(1);

    expect($notification->tries)->toBeGreaterThan(1)
        ->and($notification->backoff())->not->toBeEmpty()
        ->and($notification->backoff()[0])->toBeGreaterThan(0);
});

it('logs the user when delivery fails for good', function () {
    Log::spy();

    (new DailyTodayDigest(42))->failed(new RuntimeException('database is locked'));

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context) => $context['user_id'] === 42);
});

it('does not send before the preferred time', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 12:55:00'); // 08:55 Toronto

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
        'daily_today_email_enabled' => true,
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
        'daily_today_email_enabled' => true,
    ]);

    $this->artisan('notifications:send-daily-digest');
    Notification::assertSentToTimes($user, DailyTodayDigest::class, 1);

    // Five minutes later, still the same local day.
    CarbonImmutable::setTestNow('2026-05-10 13:07:00');
    $this->artisan('notifications:send-daily-digest');

    Notification::assertSentToTimes($user, DailyTodayDigest::class, 1);
});

it('does not send once the window after the preferred time has passed', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 19:02:00'); // 15:02 Toronto, six hours late

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
        'daily_today_email_enabled' => true,
    ]);

    $this->artisan('notifications:send-daily-digest')->assertSuccessful();

    Notification::assertNothingSentTo($user);
});

it('does not send to a user who has opted out, even with a time still set', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 13:02:00');

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
        'daily_today_email_enabled' => false,
    ]);

    $this->artisan('notifications:send-daily-digest')->assertSuccessful();

    Notification::assertNothingSentTo($user);
});

it('does not send to a brand new user who has never opted in', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 13:02:00');

    $user = loginUser();
    $user->update(['timezone' => 'America/Toronto', 'email' => 'a@example.test']);

    expect($user->fresh()->daily_today_email_enabled)->toBeFalse();

    $this->artisan('notifications:send-daily-digest')->assertSuccessful();

    Notification::assertNothingSentTo($user);
});

it('does not send to a user who has not chosen a daily email time', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 13:02:00');

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => null,
    ]);

    $this->artisan('notifications:send-daily-digest')->assertSuccessful();

    Notification::assertNothingSentTo($user);
});

it('respects the email channel preference', function () {
    Notification::fake();
    CarbonImmutable::setTestNow('2026-05-10 13:02:00');

    $user = loginUser();
    $user->update([
        'timezone' => 'America/Toronto',
        'email' => 'a@example.test',
        'daily_today_email_at' => '09:00',
        'daily_today_email_enabled' => true,
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
        'daily_today_email_enabled' => true,
    ]);

    $london = User::create([
        'household_id' => $toronto->household_id,
        'name' => 'London User',
        'email' => 'b@example.test',
        'timezone' => 'Europe/London',
        'daily_today_email_at' => '14:00',
        'daily_today_email_enabled' => true,
    ]);

    $this->artisan('notifications:send-daily-digest');

    Notification::assertSentTo($toronto, DailyTodayDigest::class);
    Notification::assertSentTo($london, DailyTodayDigest::class);
});
