<?php

namespace App\Livewire;

use App\Actions\CancelBooking;
use App\Models\Booking;
use App\Models\BookingPage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
#[Title('Cancel your meeting — Life')]
class CancelBookingPage extends Component
{
    public BookingPage $bookingPage;

    public Booking $booking;

    public bool $justCancelled = false;

    public function mount(BookingPage $bookingPage, Booking $booking): void
    {
        abort_unless($booking->booking_page_id === $bookingPage->id, 404);

        $this->bookingPage = $bookingPage->load('user');
        $this->booking = $booking;
    }

    public function cancel(CancelBooking $cancelBooking): void
    {
        if ($this->booking->isCancelled() || $this->booking->starts_at->isPast()) {
            return;
        }

        try {
            $this->booking = $cancelBooking->execute($this->booking);
            $this->justCancelled = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->addError('cancel', 'The meeting could not be cancelled. Please try again.');
        }
    }

    public function render()
    {
        return view('livewire.cancel-booking-page');
    }
}
