<?php

use App\Livewire\NotificationBell;
use App\Livewire\Profile;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('shows unread count and recent notifications', function () {
    $user = loginUser();

    $user->notify(new UserNotification([
        'title' => 'Dinner ready',
        'body' => 'Lasagna is on the table',
        'url' => '/today',
        'channels' => ['database'],
    ]));

    Livewire::test(NotificationBell::class)
        ->assertSet('unreadCount', 1)
        ->assertSee('Dinner ready')
        ->assertSee('Lasagna is on the table');
});

it('marks all notifications as read', function () {
    $user = loginUser();

    $user->notify(new UserNotification(['title' => 'A', 'channels' => ['database']]));
    $user->notify(new UserNotification(['title' => 'B', 'channels' => ['database']]));

    expect($user->unreadNotifications()->count())->toBe(2);

    Livewire::test(NotificationBell::class)
        ->call('markAllRead');

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks a single notification as read', function () {
    $user = loginUser();

    $user->notify(new UserNotification(['title' => 'Hello', 'channels' => ['database']]));
    $id = $user->notifications()->value('id');

    Livewire::test(NotificationBell::class)
        ->call('markRead', $id);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('sends mail and database channels by default', function () {
    Notification::fake();
    $user = loginUser();

    $user->notify(new UserNotification([
        'title' => 'Welcome',
        'body' => 'Glad to have you',
        'url' => 'https://example.com',
    ]));

    Notification::assertSentTo($user, UserNotification::class, function ($n, $channels) {
        return in_array('mail', $channels, true) && in_array('database', $channels, true);
    });
});

it('respects user notification preferences', function () {
    Notification::fake();
    $user = loginUser();
    $user->update(['notification_preferences' => ['site' => true, 'email' => false, 'push' => false]]);

    $user->notify(new UserNotification(['title' => 'Hi']));

    Notification::assertSentTo($user, UserNotification::class, function ($n, $channels) {
        return in_array('database', $channels, true) && ! in_array('mail', $channels, true);
    });
});

it('skips a notification entirely when no channels are enabled', function () {
    Notification::fake();
    $user = loginUser();
    $user->update(['notification_preferences' => ['site' => false, 'email' => false, 'push' => false]]);

    $user->notify(new UserNotification(['title' => 'Quiet']));

    Notification::assertNothingSentTo($user);
});

it('saves preference toggles from profile', function () {
    $user = loginUser();

    Livewire::test(Profile::class)
        ->set('notificationPrefs.email', false);

    expect($user->fresh()->wantsNotificationOn('email'))->toBeFalse()
        ->and($user->fresh()->wantsNotificationOn('site'))->toBeTrue();
});
