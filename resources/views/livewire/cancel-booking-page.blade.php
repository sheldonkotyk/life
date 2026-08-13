<div class="mx-auto max-w-5xl py-6 sm:py-12">
    <div class="mb-8 flex items-center justify-center gap-2 text-sm font-semibold text-zinc-500">
        <span class="flex size-7 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">✦</span>
        Life Bookings
    </div>

    <flux:card class="mx-auto max-w-xl text-center">
        @if ($booking->isCancelled())
            <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon.x-mark class="size-7" />
            </div>
            <flux:heading size="xl">{{ $justCancelled ? 'Your meeting is cancelled' : 'This meeting was already cancelled' }}</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                The event has been removed from {{ $bookingPage->user->name }}'s calendar.
            </flux:text>
        @elseif ($booking->starts_at->isPast())
            <flux:heading size="xl">This meeting has already happened</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                Get in touch with {{ $bookingPage->user->name }} directly if you need to follow up.
            </flux:text>
        @else
            <flux:heading size="xl">Cancel this meeting?</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                {{ $bookingPage->user->name }} will be told the time is free again.
            </flux:text>
        @endif

        <div class="mt-6 rounded-xl bg-zinc-50 p-5 text-left dark:bg-zinc-800">
            <div class="font-semibold text-zinc-900 dark:text-white">{{ $bookingPage->title }}</div>
            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ $booking->starts_at->setTimezone($booking->guest_timezone)->format('l, F j, Y · g:i A') }}
                · {{ $bookingPage->duration_minutes }} minutes
            </div>
            <div class="mt-1 text-sm text-zinc-500">{{ $booking->guest_timezone }}</div>
        </div>

        @error('cancel')
            <flux:text variant="danger" class="mt-4">{{ $message }}</flux:text>
        @enderror

        @unless ($booking->isCancelled() || $booking->starts_at->isPast())
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <flux:button wire:click="cancel" variant="danger">Cancel meeting</flux:button>
                <flux:button :href="route('booking.show', $bookingPage)" variant="ghost">Keep it</flux:button>
            </div>
        @endunless

        @if ($booking->isCancelled())
            <flux:button :href="route('booking.show', $bookingPage)" variant="ghost" class="mt-6">
                Book another time
            </flux:button>
        @endif
    </flux:card>
</div>
