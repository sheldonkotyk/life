<?php

namespace App\Http\Controllers\Api;

use App\Actions\CancelBooking;
use App\Actions\RespondToBooking;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Meeting requests, from the owner's side.
 *
 * Connecting a Google account and choosing which calendars a page reads and
 * writes stays on the web: both need Google's consent screen, and neither is
 * something you do from a phone twice a year. The app handles the settings that
 * change often and the requests that need answering today.
 */
class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $pages = $user->bookingPages()
            ->with('calendarSelections', 'googleCalendarConnection')
            ->get();

        return response()->json([
            'pages' => $pages->map(fn (BookingPage $page) => $this->pagePayload($page))->values()->all(),
            'connections' => $user->googleCalendarConnections()
                ->orderBy('google_email')
                ->get()
                ->map(fn (GoogleCalendarConnection $connection) => [
                    'id' => $connection->id,
                    'google_email' => $connection->google_email,
                    'google_name' => $connection->google_name,
                    'google_avatar_url' => $connection->google_avatar_url,
                ])->values()->all(),
        ]);
    }

    public function update(Request $request, BookingPage $bookingPage): JsonResponse
    {
        $this->authorizePage($request, $bookingPage);

        $data = $request->validate([
            'slug' => [
                'sometimes',
                'string',
                'max:80',
                'regex:/^[a-z0-9._+-]+(@[a-z0-9-]+(\.[a-z0-9-]+)+)?$/',
                'not_regex:/^\.|\.$/',
                Rule::unique('booking_pages', 'slug')->ignore($bookingPage),
            ],
            'is_enabled' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['sometimes', Rule::in([15, 30, 45, 60, 90])],
            'minimum_notice_hours' => ['sometimes', 'integer', 'min:0', 'max:168'],
            'buffer_minutes' => ['sometimes', Rule::in([0, 5, 10, 15, 30])],
            'timezone' => ['sometimes', 'timezone'],
            'availability_starts_at' => ['sometimes', 'date_format:H:i'],
            'availability_ends_at' => ['sometimes', 'date_format:H:i', 'after:availability_starts_at'],
            'available_days' => ['sometimes', 'array', 'min:1'],
            'available_days.*' => ['integer', 'between:1,7'],
        ], [
            'slug.regex' => 'Use letters, numbers, dots, dashes, or an email address.',
            'slug.not_regex' => 'Your link cannot start or end with a dot.',
            'slug.unique' => 'That link is already taken.',
        ]);

        if (isset($data['slug'])) {
            $data['slug'] = mb_strtolower(trim($data['slug']));
        }

        // A page can only answer honestly about free time once it knows which
        // calendars to read, so publishing before that is refused here too.
        if (($data['is_enabled'] ?? false) && ! $this->isConfigured($bookingPage)) {
            throw ValidationException::withMessages([
                'is_enabled' => 'Finish choosing this page\'s calendars on the web before publishing it.',
            ]);
        }

        $bookingPage->update($data);

        return response()->json($this->pagePayload($bookingPage->fresh(['calendarSelections', 'googleCalendarConnection'])));
    }

    public function bookings(Request $request, BookingPage $bookingPage): JsonResponse
    {
        $this->authorizePage($request, $bookingPage);

        return response()->json([
            'pending' => $bookingPage->bookings()
                ->where('status', Booking::STATUS_PENDING)
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Booking $booking) => $this->bookingPayload($booking))
                ->values()
                ->all(),
            'upcoming' => $bookingPage->bookings()
                ->where('status', Booking::STATUS_CONFIRMED)
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(20)
                ->get()
                ->map(fn (Booking $booking) => $this->bookingPayload($booking))
                ->values()
                ->all(),
        ]);
    }

    public function accept(Request $request, Booking $booking, RespondToBooking $respondToBooking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        try {
            $respondToBooking->accept($booking);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'booking' => 'The meeting could not be added to Google Calendar. Please try again.',
            ]);
        }

        return response()->json($this->bookingPayload($booking->fresh()));
    }

    public function decline(Request $request, Booking $booking, RespondToBooking $respondToBooking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);
        $respondToBooking->decline($booking);

        return response()->json($this->bookingPayload($booking->fresh()));
    }

    public function cancel(Request $request, Booking $booking, CancelBooking $cancelBooking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        try {
            $cancelBooking->execute($booking);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'booking' => 'The meeting could not be cancelled in Google Calendar. Please try again.',
            ]);
        }

        return response()->json($this->bookingPayload($booking->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(BookingPage $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'url' => route('booking.show', $page),
            'is_enabled' => $page->is_enabled,
            'is_configured' => $this->isConfigured($page),
            'requires_approval' => $page->requires_approval,
            'title' => $page->title,
            'description' => $page->description,
            'duration_minutes' => $page->duration_minutes,
            'minimum_notice_hours' => $page->minimum_notice_hours,
            'buffer_minutes' => $page->buffer_minutes,
            'timezone' => $page->timezone,
            'availability_starts_at' => mb_substr((string) $page->availability_starts_at, 0, 5),
            'availability_ends_at' => mb_substr((string) $page->availability_ends_at, 0, 5),
            'available_days' => $page->available_days ?? [1, 2, 3, 4, 5],
            'google_calendar_connection_id' => $page->google_calendar_connection_id,
            'google_email' => $page->googleCalendarConnection?->google_email,
            'conflict_calendars' => $page->calendarSelections
                ->where('checks_conflicts', true)
                ->map(fn (BookingCalendarSelection $selection) => $selection->google_calendar_name)
                ->values()
                ->all(),
            'destination_calendar' => $page->calendarSelections
                ->firstWhere('receives_bookings', true)?->google_calendar_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_page_id' => $booking->booking_page_id,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_title' => $booking->guest_title,
            'notes' => $booking->notes,
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'guest_timezone' => $booking->guest_timezone,
            'status' => $booking->status,
            'google_event_link' => $booking->google_event_link,
        ];
    }

    /**
     * Everything a page needs before it can be published: an account to write
     * into and at least one calendar to read for conflicts.
     */
    private function isConfigured(BookingPage $page): bool
    {
        return $page->google_calendar_connection_id !== null
            && $page->bookingCalendarSelections()->exists()
            && $page->availabilityCalendarSelections()->exists();
    }

    private function authorizePage(Request $request, BookingPage $page): void
    {
        abort_unless($page->user_id === $request->user()->id, 403);
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        $booking->loadMissing('bookingPage');
        abort_unless($booking->bookingPage?->user_id === $request->user()->id, 403);
    }
}
