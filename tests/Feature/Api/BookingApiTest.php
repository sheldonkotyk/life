<?php

use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-19 12:00:00 UTC');
    Mail::fake();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** A page whose calendars are chosen, ready to be published. */
function apiBookingPage(?User $user = null): BookingPage
{
    $user ??= loginApiUser();
    $connection = connectGoogleCalendar($user, 'work-'.uniqid().'@example.test');
    $page = BookingPage::factory()->for($user)->create([
        'google_calendar_connection_id' => $connection->id,
        'is_enabled' => false,
        'timezone' => 'UTC',
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create([
        'google_calendar_name' => 'Work',
        'checks_conflicts' => true,
    ]);

    return $page->fresh();
}

it('lists the pages and the accounts behind them', function () {
    $page = apiBookingPage();

    $response = $this->getJson('/api/booking-pages')->assertOk();

    expect($response->json('pages'))->toHaveCount(1)
        ->and($response->json('pages.0.id'))->toBe($page->id)
        ->and($response->json('pages.0.is_configured'))->toBeTrue()
        ->and($response->json('pages.0.destination_calendar'))->toBe('Work')
        ->and($response->json('connections'))->toHaveCount(1);
});

it('updates the settings that change often', function () {
    $page = apiBookingPage();

    $this->patchJson("/api/booking-pages/{$page->id}", [
        'title' => 'Coffee chat',
        'duration_minutes' => 45,
        'minimum_notice_hours' => 12,
        'available_days' => [1, 3, 5],
        'is_enabled' => true,
    ])->assertOk()->assertJsonPath('title', 'Coffee chat');

    expect($page->fresh()->duration_minutes)->toBe(45)
        ->and($page->fresh()->available_days)->toBe([1, 3, 5])
        ->and($page->fresh()->is_enabled)->toBeTrue();
});

it('refuses to publish a page with no calendars chosen', function () {
    $user = loginApiUser();
    $page = BookingPage::factory()->for($user)->create(['is_enabled' => false]);

    $this->patchJson("/api/booking-pages/{$page->id}", ['is_enabled' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors('is_enabled');
});

it('refuses a link that is already taken', function () {
    $mine = apiBookingPage();
    $stranger = User::create(['name' => 'S', 'email' => 'stranger@example.test']);
    BookingPage::factory()->for($stranger)->create(['slug' => 'taken']);

    $this->patchJson("/api/booking-pages/{$mine->id}", ['slug' => 'taken'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

it('refuses a link with spaces in it', function () {
    $page = apiBookingPage();

    $this->patchJson("/api/booking-pages/{$page->id}", ['slug' => 'not a slug'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

it('keeps another owner out of my page', function () {
    loginApiUser();
    $stranger = User::create(['name' => 'S', 'email' => 'stranger2@example.test']);
    $theirs = BookingPage::factory()->for($stranger)->create();

    $this->patchJson("/api/booking-pages/{$theirs->id}", ['title' => 'Hijack'])->assertStatus(403);
    $this->getJson("/api/booking-pages/{$theirs->id}/bookings")->assertStatus(403);
});

it('separates requests awaiting an answer from confirmed meetings', function () {
    $page = apiBookingPage();

    Booking::create([
        'booking_page_id' => $page->id,
        'guest_name' => 'Asker',
        'guest_email' => 'asker@example.test',
        'starts_at' => CarbonImmutable::parse('2026-08-20 15:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-08-20 15:30:00'),
        'guest_timezone' => 'UTC',
        'status' => Booking::STATUS_PENDING,
    ]);
    Booking::create([
        'booking_page_id' => $page->id,
        'guest_name' => 'Booked',
        'guest_email' => 'booked@example.test',
        'starts_at' => CarbonImmutable::parse('2026-08-21 15:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-08-21 15:30:00'),
        'guest_timezone' => 'UTC',
        'status' => Booking::STATUS_CONFIRMED,
    ]);
    Booking::create([
        'booking_page_id' => $page->id,
        'guest_name' => 'Past',
        'guest_email' => 'past@example.test',
        'starts_at' => CarbonImmutable::parse('2026-08-01 15:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-08-01 15:30:00'),
        'guest_timezone' => 'UTC',
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    $response = $this->getJson("/api/booking-pages/{$page->id}/bookings")->assertOk();

    expect($response->json('pending'))->toHaveCount(1)
        ->and($response->json('pending.0.guest_name'))->toBe('Asker')
        ->and($response->json('upcoming'))->toHaveCount(1)
        ->and($response->json('upcoming.0.guest_name'))->toBe('Booked');
});

it('declines a request and frees the time', function () {
    $page = apiBookingPage();
    $booking = Booking::create([
        'booking_page_id' => $page->id,
        'guest_name' => 'Asker',
        'guest_email' => 'asker@example.test',
        'starts_at' => CarbonImmutable::parse('2026-08-20 15:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-08-20 15:30:00'),
        'guest_timezone' => 'UTC',
        'status' => Booking::STATUS_PENDING,
    ]);

    $this->postJson("/api/bookings/{$booking->id}/decline")
        ->assertOk()
        ->assertJsonPath('status', Booking::STATUS_REJECTED);
});

it('keeps a stranger from answering my requests', function () {
    loginApiUser();
    $stranger = User::create(['name' => 'S', 'email' => 'stranger3@example.test']);
    $theirPage = BookingPage::factory()->for($stranger)->create();
    $booking = Booking::create([
        'booking_page_id' => $theirPage->id,
        'guest_name' => 'Asker',
        'guest_email' => 'asker@example.test',
        'starts_at' => CarbonImmutable::parse('2026-08-20 15:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-08-20 15:30:00'),
        'guest_timezone' => 'UTC',
        'status' => Booking::STATUS_PENDING,
    ]);

    $this->postJson("/api/bookings/{$booking->id}/decline")->assertStatus(403);
});
