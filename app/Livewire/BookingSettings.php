<?php

namespace App\Livewire;

use App\Actions\CancelBooking;
use App\Contracts\GoogleCalendar;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
#[Title('Booking settings — Life')]
class BookingSettings extends Component
{
    /** @var array<int, string> */
    public const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    public ?BookingPage $bookingPage = null;

    public string $slug = '';

    public bool $isEnabled = false;

    public string $title = '';

    public string $description = '';

    public int $durationMinutes = 30;

    public int $minimumNoticeHours = 2;

    public int $bufferMinutes = 0;

    public string $timezone = 'UTC';

    public string $availabilityStartsAt = '09:00';

    public string $availabilityEndsAt = '17:00';

    /** @var list<int|string> */
    public array $availableDays = [1, 2, 3, 4, 5];

    public string $bookingCalendarKey = '';

    /** @var list<string> */
    public array $availabilityCalendarKeys = [];

    /** @var list<array{key: string, connection_id: int, connection_email: string, id: string, name: string, primary: bool, access_role: string}> */
    public array $calendars = [];

    /** @var array<int, string> */
    public array $accountErrors = [];

    public function mount(GoogleCalendar $googleCalendar): void
    {
        $connection = auth()->user()->googleCalendarConnections()->orderBy('google_email')->first();

        if (! $connection) {
            return;
        }

        $this->selectPage($this->pageFor($connection), $googleCalendar);
    }

    /**
     * Switch which connected account's page is being edited.
     */
    public function editAccount(int $connectionId, GoogleCalendar $googleCalendar): void
    {
        $connection = auth()->user()->googleCalendarConnections()->findOrFail($connectionId);

        $this->resetErrorBag();
        $this->selectPage($this->pageFor($connection), $googleCalendar);
    }

    public function refreshCalendars(GoogleCalendar $googleCalendar): void
    {
        $this->loadCalendars($googleCalendar);
    }

    public function save(GoogleCalendar $googleCalendar): void
    {
        if (! $this->bookingPage) {
            $this->addError('isEnabled', 'Connect Google Calendar before publishing your page.');

            return;
        }

        $this->availableDays = array_values(array_map('intval', $this->availableDays));
        $submittedBookingCalendarKey = $this->bookingCalendarKey;
        $submittedAvailabilityCalendarKeys = $this->availabilityCalendarKeys;
        $this->fetchCalendars($googleCalendar);
        $this->bookingCalendarKey = $submittedBookingCalendarKey;
        $this->availabilityCalendarKeys = $submittedAvailabilityCalendarKeys;

        // Links are case-insensitive, and an email typed with capitals is still
        // the same address.
        $this->slug = mb_strtolower(trim($this->slug));

        $this->validate([
            'slug' => [
                'required',
                'string',
                'max:80',
                // A handle or an email address; both sit in one path segment.
                'regex:/^[a-z0-9._+-]+(@[a-z0-9-]+(\.[a-z0-9-]+)+)?$/',
                'not_regex:/^\.|\.$/',
                Rule::unique('booking_pages', 'slug')->ignore($this->bookingPage),
            ],
            'isEnabled' => ['boolean'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'durationMinutes' => ['required', Rule::in([15, 30, 45, 60, 90])],
            'minimumNoticeHours' => ['required', 'integer', 'min:0', 'max:168'],
            'bufferMinutes' => ['required', Rule::in([0, 5, 10, 15, 30])],
            'timezone' => ['required', 'timezone'],
            'availabilityStartsAt' => ['required', 'date_format:H:i'],
            'availabilityEndsAt' => ['required', 'date_format:H:i', 'after:availabilityStartsAt'],
            'availableDays' => ['required', 'array', 'min:1'],
            'availableDays.*' => ['required', 'integer', 'between:1,7'],
            'bookingCalendarKey' => ['required', 'string'],
            'availabilityCalendarKeys' => ['required', 'array', 'min:1', 'max:50'],
            'availabilityCalendarKeys.*' => ['required', 'string'],
        ], [
            'slug.regex' => 'Use letters, numbers, dots, dashes, or an email address.',
            'slug.not_regex' => 'Your link cannot start or end with a dot.',
            'slug.unique' => 'That link is already taken.',
        ]);

        if ($this->accountErrors !== []) {
            $this->addError('calendars', 'Refresh or reconnect every Google account before saving calendar changes.');

            return;
        }

        $knownCalendarKeys = collect($this->calendars)->pluck('key');
        $bookingCalendar = collect($this->calendars)->firstWhere('key', $this->bookingCalendarKey);

        if (! $bookingCalendar
            || ! in_array($bookingCalendar['access_role'], ['owner', 'writer'], true)
        ) {
            $this->addError('bookingCalendarKey', 'Choose a calendar that Google allows you to edit.');

            return;
        }

        // Conflicts may be read from any connected account, but the meeting is
        // written to the account this page belongs to.
        if ($bookingCalendar['connection_id'] !== $this->bookingPage->google_calendar_connection_id) {
            $this->addError('bookingCalendarKey', 'This page books into its own Google account. Choose one of its calendars.');

            return;
        }

        if (collect($this->availabilityCalendarKeys)->diff($knownCalendarKeys)->isNotEmpty()) {
            $this->addError('availabilityCalendarKeys', 'One of the selected calendars is no longer available.');

            return;
        }

        if ($this->isEnabled && auth()->user()->googleCalendarConnections()->doesntExist()) {
            $this->addError('isEnabled', 'Connect Google Calendar before publishing your page.');

            return;
        }

        DB::transaction(function () use ($bookingCalendar): void {
            $availabilityCalendars = collect($this->calendars)
                ->whereIn('key', $this->availabilityCalendarKeys)
                ->keyBy('key');
            $selectedCalendars = $availabilityCalendars
                ->put($this->bookingCalendarKey, $bookingCalendar);

            $this->bookingPage->update([
                'slug' => $this->slug,
                'is_enabled' => $this->isEnabled,
                'title' => $this->title,
                'description' => blank($this->description) ? null : trim($this->description),
                'duration_minutes' => $this->durationMinutes,
                'minimum_notice_hours' => $this->minimumNoticeHours,
                'buffer_minutes' => $this->bufferMinutes,
                'timezone' => $this->timezone,
                'availability_starts_at' => $this->availabilityStartsAt,
                'availability_ends_at' => $this->availabilityEndsAt,
                'available_days' => $this->availableDays,
            ]);

            $this->bookingPage->calendarSelections()->delete();

            foreach ($selectedCalendars as $key => $calendar) {
                $this->bookingPage->calendarSelections()->create([
                    'google_calendar_connection_id' => $calendar['connection_id'],
                    'google_calendar_id' => $calendar['id'],
                    'google_calendar_name' => $calendar['name'],
                    'checks_conflicts' => $availabilityCalendars->has($key),
                    'receives_bookings' => $key === $this->bookingCalendarKey,
                ]);
            }
        });

        $this->bookingPage->unsetRelations();
        session()->flash('status', 'Booking settings saved.');
    }

    public function disconnect(int $connectionId, GoogleCalendar $googleCalendar): void
    {
        $connection = auth()->user()->googleCalendarConnections()
            ->with('oauthToken')
            ->findOrFail($connectionId);

        try {
            $googleCalendar->revoke($connection);
        } catch (Throwable $exception) {
            report($exception);
        }

        $editingDisconnectedAccount = $this->bookingPage?->google_calendar_connection_id === $connection->id;

        // The account's own page goes with it; other pages may have been reading
        // its calendars for conflicts, so they are unpublished if that leaves
        // them unable to answer honestly.
        $connection->delete();

        $remaining = auth()->user()->googleCalendarConnections()->orderBy('google_email')->first();

        if (! $remaining) {
            $this->bookingPage = null;
            $this->calendars = [];
            $this->accountErrors = [];
            session()->flash('status', 'Google account disconnected. Its booking page was removed.');

            return;
        }

        foreach (auth()->user()->bookingPages()->where('is_enabled', true)->get() as $page) {
            if (! $page->isReady()) {
                $page->update(['is_enabled' => false]);
            }
        }

        $this->selectPage(
            $editingDisconnectedAccount ? $this->pageFor($remaining) : $this->bookingPage->fresh(),
            $googleCalendar,
        );

        session()->flash('status', $editingDisconnectedAccount
            ? 'Google account disconnected. Its booking page was removed.'
            : 'Google account disconnected. Pages that relied on its calendars were unpublished.');
    }

    public function cancelBooking(int $bookingId, CancelBooking $cancelBooking): void
    {
        $booking = $this->bookingPage->bookings()->findOrFail($bookingId);

        try {
            $cancelBooking->execute($booking);
        } catch (Throwable $exception) {
            report($exception);

            $this->addError('bookings', 'The meeting could not be cancelled in Google Calendar. Please try again.');

            return;
        }

        session()->flash('status', 'Meeting cancelled. The guest was notified by Google Calendar.');
    }

    public function render()
    {
        $connections = auth()->user()->googleCalendarConnections()
            ->with('oauthToken')
            ->orderBy('google_email')
            ->get();
        // Each card describes that account's own page, not the one being edited.
        $pages = auth()->user()->bookingPages()
            ->with('calendarSelections')
            ->get()
            ->keyBy('google_calendar_connection_id');

        $accountSummaries = $connections->map(function (GoogleCalendarConnection $connection) use ($pages): array {
            $token = $connection->oauthToken;
            $page = $pages->get($connection->id);
            $accountSelections = $page?->calendarSelections ?? collect();

            return [
                'connection' => $connection,
                'token' => $token,
                'page' => $page,
                'calendars' => collect($this->calendars)->where('connection_id', $connection->id)->values()->all(),
                'calendar_count' => collect($this->calendars)->where('connection_id', $connection->id)->count(),
                'conflict_count' => $accountSelections->where('checks_conflicts', true)->count(),
                'destination' => $accountSelections->firstWhere('receives_bookings', true),
                'scopes' => collect($token?->scopes() ?? [])
                    ->map(fn (string $scope): string => str($scope)->afterLast('/')->headline()->toString())
                    ->all(),
            ];
        });

        return view('livewire.booking-settings', [
            'connections' => $connections,
            'accountSummaries' => $accountSummaries,
            'days' => self::DAYS,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'publicUrl' => $this->bookingPage ? route('booking.show', $this->bookingPage) : null,
            'upcomingBookings' => $this->bookingPage
                ? $this->bookingPage->bookings()
                    ->where('status', Booking::STATUS_CONFIRMED)
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->limit(10)
                    ->get()
                : collect(),
        ]);
    }

    private function pageFor(GoogleCalendarConnection $connection): BookingPage
    {
        $user = auth()->user();

        return BookingPage::firstOrCreate(
            ['google_calendar_connection_id' => $connection->id],
            [
                'user_id' => $user->id,
                'slug' => BookingPage::uniqueSlugFor($connection->google_email),
                'title' => 'Meet with '.$user->name,
                'timezone' => $user->getTimezone(),
                'available_days' => [1, 2, 3, 4, 5],
            ],
        );
    }

    private function selectPage(BookingPage $bookingPage, GoogleCalendar $googleCalendar): void
    {
        $this->bookingPage = $bookingPage;
        $this->loadSettings();
        $this->loadCalendars($googleCalendar);
    }

    private function loadSettings(): void
    {
        $this->bookingPage->refresh();
        $this->slug = $this->bookingPage->slug;
        $this->isEnabled = $this->bookingPage->is_enabled;
        $this->title = $this->bookingPage->title;
        $this->description = $this->bookingPage->description ?? '';
        $this->durationMinutes = $this->bookingPage->duration_minutes;
        $this->minimumNoticeHours = $this->bookingPage->minimum_notice_hours;
        $this->bufferMinutes = $this->bookingPage->buffer_minutes;
        $this->timezone = $this->bookingPage->timezone;
        $this->availabilityStartsAt = mb_substr($this->bookingPage->availability_starts_at, 0, 5);
        $this->availabilityEndsAt = mb_substr($this->bookingPage->availability_ends_at, 0, 5);
        $this->availableDays = $this->bookingPage->available_days ?? [1, 2, 3, 4, 5];
        $this->bookingCalendarKey = '';
        $this->availabilityCalendarKeys = [];
    }

    private function loadCalendars(GoogleCalendar $googleCalendar): void
    {
        $this->fetchCalendars($googleCalendar);

        $selections = $this->bookingPage->calendarSelections()->get();
        $this->availabilityCalendarKeys = $selections
            ->where('checks_conflicts', true)
            ->map(fn ($selection): string => $this->calendarKey(
                $selection->google_calendar_connection_id,
                $selection->google_calendar_id,
            ))
            ->filter(fn (string $key): bool => collect($this->calendars)->contains('key', $key))
            ->values()
            ->all();
        $destination = $selections->firstWhere('receives_bookings', true);
        $this->bookingCalendarKey = $destination
            ? $this->calendarKey($destination->google_calendar_connection_id, $destination->google_calendar_id)
            : '';

        if ($selections->isEmpty()) {
            // A fresh page starts on its own account: every primary calendar the
            // user has checks conflicts, and this account's primary receives.
            $ownCalendars = collect($this->calendars)
                ->where('connection_id', $this->bookingPage->google_calendar_connection_id);
            $writable = fn (array $calendar): bool => in_array($calendar['access_role'], ['owner', 'writer'], true);

            $this->availabilityCalendarKeys = collect($this->calendars)
                ->where('primary', true)
                ->pluck('key')
                ->values()
                ->all();
            $this->bookingCalendarKey = $ownCalendars->where('primary', true)->first($writable)['key']
                ?? $ownCalendars->first($writable)['key']
                ?? '';
        }
    }

    private function fetchCalendars(GoogleCalendar $googleCalendar): void
    {
        $this->calendars = [];
        $this->accountErrors = [];
        $connections = auth()->user()->googleCalendarConnections()
            ->with('oauthToken')
            ->orderBy('google_email')
            ->get();

        foreach ($connections as $connection) {
            try {
                foreach ($googleCalendar->calendars($connection) as $calendar) {
                    $this->calendars[] = [
                        'key' => $this->calendarKey($connection->id, $calendar['id']),
                        'connection_id' => $connection->id,
                        'connection_email' => $connection->google_email,
                        ...$calendar,
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->accountErrors[$connection->id] = 'Calendars could not be loaded. Reconnect this account and try again.';
            }
        }
    }

    private function calendarKey(int $connectionId, string $calendarId): string
    {
        return hash('sha256', $connectionId.'|'.$calendarId);
    }
}
