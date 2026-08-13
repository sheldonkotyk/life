<?php

namespace App\Livewire;

use App\Actions\RescheduleBooking;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app', ['chrome' => false])]
#[Title('Move your meeting — Life')]
class RescheduleBookingPage extends Component
{
    public BookingPage $bookingPage;

    public Booking $booking;

    public string $selectedDate = '';

    public string $selectedStart = '';

    public bool $justRescheduled = false;

    public function mount(BookingPage $bookingPage, Booking $booking): void
    {
        abort_unless($booking->booking_page_id === $bookingPage->id, 404);

        $this->bookingPage = $bookingPage->load([
            'user',
            'availabilityCalendarSelections.connection.oauthToken',
            'bookingCalendarSelections.connection.oauthToken',
        ]);
        $this->booking = $booking;
        $this->selectedDate = $booking->starts_at->setTimezone($bookingPage->timezone)->toDateString();
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

    public function reschedule(RescheduleBooking $rescheduleBooking): void
    {
        $validated = $this->validate([
            'selectedDate' => ['required', 'date_format:Y-m-d'],
            'selectedStart' => ['required', 'date'],
        ], [
            'selectedStart.required' => 'Choose a time for your meeting.',
            'selectedDate.required' => 'Choose a date for your meeting.',
        ]);

        $startsAt = CarbonImmutable::parse($validated['selectedStart']);

        if ($startsAt->setTimezone($this->bookingPage->timezone)->toDateString() !== $validated['selectedDate']) {
            $this->addError('selectedStart', 'Choose a time from the selected day.');

            return;
        }

        try {
            $this->booking = $rescheduleBooking->execute($this->booking, $startsAt);
            $this->justRescheduled = true;
        } catch (LockTimeoutException) {
            $this->booking->refresh();
            $this->addError('selectedStart', 'Someone else is booking that time. Please try another.');
        } catch (ValidationException $exception) {
            $this->booking->refresh();

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->booking->refresh();
            $this->addError('selectedStart', 'The meeting could not be moved in Google Calendar. Please try again.');
        }
    }

    public function render()
    {
        $slots = [];
        $calendarError = null;

        if (! $this->justRescheduled && ! $this->booking->isCancelled()) {
            try {
                $slots = app(AvailabilityService::class)->slots(
                    $this->bookingPage,
                    $this->selectedDate,
                    $this->booking,
                );
            } catch (Throwable $exception) {
                report($exception);
                $calendarError = 'Availability is temporarily unavailable. Please try again shortly.';
            }
        }

        return view('livewire.reschedule-booking-page', [
            'availableSlots' => $slots,
            'calendarError' => $calendarError,
            'minimumDate' => CarbonImmutable::now($this->bookingPage->timezone)->toDateString(),
            'maximumDate' => CarbonImmutable::now($this->bookingPage->timezone)
                ->addDays(AvailabilityService::MAX_DAYS_AHEAD)
                ->toDateString(),
        ]);
    }
}
