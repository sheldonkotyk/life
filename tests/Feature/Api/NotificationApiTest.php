<?php

use App\Notifications\UserNotification;

it('lists recent notifications with the unread count', function () {
    $user = loginApiUser();
    $user->notify(new UserNotification([
        'title' => 'Dinner ready',
        'body' => 'Lasagna is on the table',
        'url' => '/today',
        'channels' => ['database'],
    ]));

    $response = $this->getJson('/api/notifications')->assertOk();

    expect($response->json('unread_count'))->toBe(1)
        ->and($response->json('notifications.0.title'))->toBe('Dinner ready')
        ->and($response->json('notifications.0.body'))->toBe('Lasagna is on the table')
        ->and($response->json('notifications.0.url'))->toBe('/today')
        ->and($response->json('notifications.0.read_at'))->toBeNull();
});

it('marks one notification read', function () {
    $user = loginApiUser();
    $user->notify(new UserNotification(['title' => 'A', 'channels' => ['database']]));
    $id = $user->notifications()->first()->id;

    $this->postJson("/api/notifications/{$id}/read")->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks everything read', function () {
    $user = loginApiUser();
    $user->notify(new UserNotification(['title' => 'A', 'channels' => ['database']]));
    $user->notify(new UserNotification(['title' => 'B', 'channels' => ['database']]));

    $this->postJson('/api/notifications/read-all')->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('will not read somebody else\'s notification', function () {
    $mine = loginApiUser();
    $mine->notify(new UserNotification(['title' => 'Mine', 'channels' => ['database']]));

    $this->postJson('/api/notifications/00000000-0000-0000-0000-000000000000/read')->assertOk();

    expect($mine->fresh()->unreadNotifications()->count())->toBe(1);
});
