<?php

namespace App\Livewire;

use App\Contracts\GoogleCalendar;
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

    public BookingPage $bookingPage;

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
        $user = auth()->user();
        $this->bookingPage = BookingPage::firstOrCreate(
            ['user_id' => $user->id],
            [
                'slug' => BookingPage::uniqueSlugFor($user),
                'title' => 'Meet with '.$user->name,
                'timezone' => $user->getTimezone(),
                'available_days' => [1, 2, 3, 4, 5],
            ],
        );

        $this->loadSettings();

        if ($user->googleCalendarConnections()->exists()) {
            $this->loadCalendars($googleCalendar);
        }
    }

    public function refreshCalendars(GoogleCalendar $googleCalendar): void
    {
        $this->loadCalendars($googleCalendar);
    }

    public function save(GoogleCalendar $googleCalendar): void
    {
        $this->availableDays = array_values(array_map('intval', $this->availableDays));
        $submittedBookingCalendarKey = $this->bookingCalendarKey;
        $submittedAvailabilityCalendarKeys = $this->availabilityCalendarKeys;
        $this->fetchCalendars($googleCalendar);
        $this->bookingCalendarKey = $submittedBookingCalendarKey;
        $this->availabilityCalendarKeys = $submittedAvailabilityCalendarKeys;

        $this->validate([
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                'max:80',
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

        $connection->delete();
        $this->bookingPage->unsetRelations();

        if ($this->bookingPage->is_enabled && ! $this->bookingPage->isReady()) {
            $this->bookingPage->update(['is_enabled' => false]);
        }

        $this->loadSettings();
        $this->loadCalendars($googleCalendar);
        session()->flash('status', 'Google account disconnected. Remaining calendar selections were kept.');
    }

    public function render()
    {
        $connections = auth()->user()->googleCalendarConnections()
            ->with('oauthToken')
            ->orderBy('google_email')
            ->get();
        $selections = $this->bookingPage->calendarSelections()
            ->with('connection:id,google_email')
            ->get();

        $accountSummaries = $connections->map(function (GoogleCalendarConnection $connection) use ($selections): array {
            $token = $connection->oauthToken;
            $accountSelections = $selections->where('google_calendar_connection_id', $connection->id);

            return [
                'connection' => $connection,
                'token' => $token,
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
            'publicUrl' => route('booking.show', $this->bookingPage),
            'upcomingBookings' => $this->bookingPage->bookings()
                ->where('status', 'confirmed')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(10)
                ->get(),
        ]);
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
            $primaryCalendars = collect($this->calendars)->where('primary', true);
            $writablePrimary = $primaryCalendars
                ->first(fn (array $calendar): bool => in_array($calendar['access_role'], ['owner', 'writer'], true));
            $writableCalendar = collect($this->calendars)
                ->first(fn (array $calendar): bool => in_array($calendar['access_role'], ['owner', 'writer'], true));

            $this->availabilityCalendarKeys = $primaryCalendars->pluck('key')->values()->all();
            $this->bookingCalendarKey = $writablePrimary['key'] ?? $writableCalendar['key'] ?? '';
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
