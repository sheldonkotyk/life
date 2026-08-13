<?php

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    CarbonImmutable::setTestNow('2026-08-11 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

/**
 * Guest pages only. The booking page itself needs live Google credentials to
 * render its times, so slot rendering is covered by the Livewire feature tests
 * where freebusy can be faked; these keep the guest pages honest in a browser.
 */
function guestBooking(array $attributes = []): Booking
{
    $page = BookingPage::factory()->for(User::factory()->create(['name' => 'Taylor Owner']))->create([
        'slug' => 'taylor-owner',
        'title' => 'Project chat',
        'timezone' => 'UTC',
    ]);

    return Booking::factory()->for($page)->create([
        'guest_name' => 'Sam Guest',
        'guest_timezone' => 'UTC',
        'starts_at' => CarbonImmutable::parse('2026-08-14 15:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-14 15:30:00', 'UTC'),
        ...$attributes,
    ]);
}

it('shows a guest their meeting before they confirm cancelling it', function () {
    $booking = guestBooking();

    $page = visit($booking->cancelUrl());

    $page->assertSee('Cancel this meeting?')
        ->assertSee('Project chat')
        ->assertSee('Friday, August 14, 2026 · 3:00 PM')
        ->assertSee('Cancel meeting')
        ->assertNoJavaScriptErrors();
});

it('tells a guest when their meeting was already cancelled', function () {
    $booking = guestBooking(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);

    $page = visit($booking->cancelUrl());

    $page->assertSee('This meeting was already cancelled')
        ->assertDontSee('Cancel meeting')
        ->assertNoJavaScriptErrors();
});

it('asks a guest to pick a time before moving a meeting', function () {
    $booking = guestBooking();

    $page = visit($booking->rescheduleUrl());

    $page->assertSee('Move your meeting')
        ->assertSee('Currently booked for')
        ->assertNoJavaScriptErrors();

    $page->click('Move meeting');

    $page->assertSee('Choose a time for your meeting.');
});
