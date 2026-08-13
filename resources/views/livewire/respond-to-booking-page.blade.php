<div class="mx-auto max-w-5xl py-6 sm:py-12">
    <flux:card class="mx-auto max-w-xl text-center">
        @if ($failure)
            <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                <flux:icon.exclamation-triangle class="size-7" />
            </div>
            <flux:heading size="xl">That didn't go through</flux:heading>
            <flux:text variant="subtle" class="mt-2">{{ $failure }}</flux:text>
        @elseif ($booking->status === \App\Models\Booking::STATUS_CONFIRMED)
            <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                <flux:icon.check class="size-7" />
            </div>
            <flux:heading size="xl">Accepted</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                The event is confirmed on your calendar and {{ $booking->guest_name }} has been invited.
            </flux:text>
        @elseif ($booking->isRejected())
            <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon.x-mark class="size-7" />
            </div>
            <flux:heading size="xl">Declined</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                The hold has been removed from your calendar and the time is bookable again.
                {{ $booking->guest_name }} was never sent an invitation.
            </flux:text>
        @else
            <flux:heading size="xl">Already answered</flux:heading>
            <flux:text variant="subtle" class="mt-2">This request is no longer waiting on you.</flux:text>
        @endif

        <div class="mt-6 rounded-xl bg-zinc-50 p-5 text-left dark:bg-zinc-800">
            <div class="font-semibold text-zinc-900 dark:text-white">{{ $booking->guest_title ?: $bookingPage->title }}</div>
            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ $booking->guest_name }} · {{ $booking->guest_email }}
            </div>
            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ $booking->starts_at->setTimezone($bookingPage->timezone)->format('l, F j, Y · g:i A') }}
                · {{ $bookingPage->duration_minutes }} minutes
            </div>
        </div>

        <flux:button :href="route('booking.settings')" variant="ghost" class="mt-6">Open booking settings</flux:button>
    </flux:card>
</div>
