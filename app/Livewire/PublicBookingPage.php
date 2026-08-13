<?php

namespace App\Livewire;

use App\Actions\CreateBooking;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app', ['chrome' => false])]
#[Title('Request a meeting — Life')]
class PublicBookingPage extends Component
{
    public BookingPage $bookingPage;

    public string $selectedDate = '';

    public string $selectedStart = '';

    public string $guestName = '';

    public string $guestEmail = '';

    public string $meetingTitle = '';

    public string $notes = '';

    public string $guestTimezone = 'UTC';

    public ?Booking $booking = null;

    public function mount(BookingPage $bookingPage): void
    {
        $bookingPage->load([
            'user',
            'availabilityCalendarSelections.connection.oauthToken',
            'bookingCalendarSelections.connection.oauthToken',
        ]);
        abort_unless($bookingPage->isReady(), 404);

        $this->bookingPage = $bookingPage;
        $this->guestTimezone = $bookingPage->timezone;
        $this->selectedDate = $this->firstAvailableDate();
    }

    public function updatedSelectedDate(): void
    {
        $this->selectedStart = '';
        $this->resetErrorBag('selectedStart');
    }

    public function selectSlot(string $startsAt): void
    {
        $this->selectedStart = $startsAt;
        $this->resetErrorBag('selectedStart');
    }

    public function book(CreateBooking $createBooking): void
    {
        $validated = $this->validate([
            'selectedDate' => ['required', 'date_format:Y-m-d'],
            'selectedStart' => ['required', 'date'],
            'guestName' => ['required', 'string', 'max:120'],
            'guestEmail' => ['required', 'email', 'max:255'],
            'meetingTitle' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'guestTimezone' => ['required', 'timezone'],
        ], [
            'selectedDate.required' => 'Choose a date for your meeting.',
            'selectedStart.required' => 'Choose a time for your meeting.',
            'guestName.required' => 'Please tell us your name.',
            'guestEmail.required' => 'Please give us an email for the invitation.',
            'guestEmail.email' => 'That email address does not look right.',
        ]);

        $startsAt = CarbonImmutable::parse($validated['selectedStart']);
        if ($startsAt->setTimezone($this->bookingPage->timezone)->toDateString() !== $validated['selectedDate']) {
            $this->addError('selectedStart', 'Choose a time from the selected day.');

            return;
        }

        $rateLimitKey = 'public-booking:'.sha1($validated['guestEmail'].'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->addError('guestEmail', 'Too many booking attempts. Please wait a minute and try again.');

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        try {
            $this->booking = $createBooking->execute($this->bookingPage, $startsAt, [
                'guest_name' => trim($validated['guestName']),
                'guest_title' => blank($validated['meetingTitle']) ? null : trim($validated['meetingTitle']),
                'guest_email' => mb_strtolower(trim($validated['guestEmail'])),
                'notes' => blank($validated['notes']) ? null : trim($validated['notes']),
                'guest_timezone' => $validated['guestTimezone'],
            ]);
        } catch (LockTimeoutException) {
            $this->addError('selectedStart', 'Someone else is booking that time. Please try another.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('selectedStart', 'The meeting could not be added to Google Calendar. Please try again.');
        }
    }

    public function render()
    {
        $slots = [];
        $calendarError = null;

        if (! $this->booking) {
            try {
                $slots = app(AvailabilityService::class)->slots($this->bookingPage, $this->selectedDate);
            } catch (Throwable $exception) {
                report($exception);
                $calendarError = 'Availability is temporarily unavailable. Please try again shortly.';
            }
        }

        return view('livewire.public-booking-page', [
            'availableSlots' => $slots,
            'calendarError' => $calendarError,
            'minimumDate' => CarbonImmutable::now($this->bookingPage->timezone)->toDateString(),
            'maximumDate' => CarbonImmutable::now($this->bookingPage->timezone)
                ->addDays(AvailabilityService::MAX_DAYS_AHEAD)
                ->toDateString(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    private function firstAvailableDate(): string
    {
        $date = CarbonImmutable::now($this->bookingPage->timezone)->startOfDay();

        for ($offset = 0; $offset < 8; $offset++) {
            $candidate = $date->addDays($offset);
            if (in_array($candidate->dayOfWeekIso, $this->bookingPage->available_days, true)) {
                return $candidate->toDateString();
            }
        }

        return $date->toDateString();
    }
}
