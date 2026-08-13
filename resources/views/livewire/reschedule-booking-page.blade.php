<div class="mx-auto max-w-5xl py-6 sm:py-12">

    @if ($justRescheduled)
        <flux:card class="mx-auto max-w-xl text-center">
            <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                <flux:icon.check class="size-7" />
            </div>
            <flux:heading size="xl">Your meeting has moved</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                An updated invitation was sent to {{ $booking->guest_email }}.
            </flux:text>

            <div class="mt-6 rounded-xl bg-zinc-50 p-5 text-left dark:bg-zinc-800">
                <div class="font-semibold text-zinc-900 dark:text-white">{{ $booking->guest_title ?: $bookingPage->title }}</div>
                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $booking->starts_at->setTimezone($booking->guest_timezone)->format('l, F j, Y · g:i A') }}
                    · {{ $bookingPage->duration_minutes }} minutes
                </div>
                <div class="mt-1 text-sm text-zinc-500">{{ $booking->guest_timezone }}</div>
            </div>
        </flux:card>
    @elseif ($booking->isCancelled() || $booking->isRejected())
        <flux:card class="mx-auto max-w-xl text-center">
            <flux:heading size="xl">{{ $booking->isRejected() ? 'This request was declined' : 'This meeting was cancelled' }}</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                It can no longer be moved, but you're welcome to book a new time.
            </flux:text>
            <flux:button :href="route('booking.show', $bookingPage)" variant="ghost" class="mt-6">
                Book another time
            </flux:button>
        </flux:card>
    @else
        <div class="mx-auto max-w-xl space-y-6">
            <flux:card>
                <flux:heading size="lg">Move your meeting</flux:heading>
                <flux:text variant="subtle" class="mt-1">
                    with {{ $bookingPage->user->name }} · {{ $bookingPage->duration_minutes }} minutes
                </flux:text>

                @if ($booking->isAwaitingApproval())
                    <flux:callout icon="clock" class="mt-4">
                        This time is still held while it waits to be accepted. Moving it keeps the hold and the request.
                    </flux:callout>
                @endif

                <div class="mt-5 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                    <div class="text-zinc-500">{{ $booking->isAwaitingApproval() ? 'Currently held for' : 'Currently booked for' }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white">
                        {{ $booking->starts_at->setTimezone($booking->guest_timezone)->format('l, F j, Y · g:i A') }}
                    </div>
                    <div class="mt-1 text-zinc-500">{{ $booking->guest_timezone }}</div>
                </div>
            </flux:card>

            <flux:card>
                <form wire:submit="reschedule" class="space-y-7">
                    <flux:input
                        wire:model.live="selectedDate"
                        type="date"
                        label="Pick a new date"
                        :min="$minimumDate"
                        :max="$maximumDate"
                    />

                    <flux:field>
                        <flux:label>Choose a time</flux:label>
                        @if ($calendarError)
                            <flux:callout color="red" icon="exclamation-triangle">{{ $calendarError }}</flux:callout>
                        @elseif ($availableSlots === [])
                            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
                                No times are available on this day. Try another date.
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($availableSlots as $slot)
                                    <flux:button
                                        wire:key="slot-{{ $slot['start'] }}"
                                        type="button"
                                        wire:click="selectSlot('{{ $slot['start'] }}')"
                                        :variant="$selectedStart === $slot['start'] ? 'primary' : 'outline'"
                                        class="w-full!"
                                    >
                                        {{ $slot['label'] }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @endif
                        <flux:error name="selectedStart" />
                    </flux:field>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <flux:button type="submit" variant="primary">Move meeting</flux:button>
                        <flux:button :href="route('booking.show', $bookingPage)" variant="ghost">Keep the current time</flux:button>
                    </div>
                </form>
            </flux:card>
        </div>
    @endif
</div>
