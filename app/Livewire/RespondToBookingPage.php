<?php

namespace App\Livewire;

use App\Actions\RespondToBooking;
use App\Models\Booking;
use App\Models\BookingPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * The page the answer links in the tentative calendar event lead to, so the
 * owner can accept or decline without signing in.
 */
#[Layout('components.layouts.app', ['chrome' => false])]
#[Title('Respond to a request — Life')]
class RespondToBookingPage extends Component
{
    public BookingPage $bookingPage;

    public Booking $booking;

    public string $answer = '';

    public ?string $failure = null;

    public function mount(BookingPage $bookingPage, Booking $booking, string $answer, RespondToBooking $respondToBooking): void
    {
        abort_unless($booking->booking_page_id === $bookingPage->id, 404);
        abort_unless(in_array($answer, ['accept', 'decline'], true), 404);

        $this->bookingPage = $bookingPage->load('user');
        $this->booking = $booking;
        $this->answer = $answer;

        if (! $booking->isAwaitingApproval()) {
            return;
        }

        try {
            $this->booking = $answer === 'accept'
                ? $respondToBooking->accept($booking)
                : $respondToBooking->decline($booking);
        } catch (ValidationException $exception) {
            $this->failure = $exception->validator->errors()->first();
        } catch (Throwable $exception) {
            report($exception);
            $this->failure = 'Google Calendar could not be updated. Please try again from your booking settings.';
        }
    }

    public function render()
    {
        return view('livewire.respond-to-booking-page');
    }
}
