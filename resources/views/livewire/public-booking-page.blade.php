<div class="mx-auto max-w-5xl py-6 sm:py-12">

    @if ($booking)
        <flux:card class="mx-auto max-w-xl text-center">
            <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                <flux:icon.check class="size-7" />
            </div>
            <flux:heading size="xl">You're booked</flux:heading>
            <flux:text variant="subtle" class="mt-2">
                A calendar invitation was sent to {{ $booking->guest_email }}.
            </flux:text>

            <div class="mt-6 rounded-xl bg-zinc-50 p-5 text-left dark:bg-zinc-800">
                <div class="font-semibold text-zinc-900 dark:text-white">{{ $booking->guest_title ?: $bookingPage->title }}</div>
                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $booking->starts_at->setTimezone($bookingPage->timezone)->format('l, F j, Y · g:i A') }}
                    · {{ $bookingPage->duration_minutes }} minutes
                </div>
                <div class="mt-1 text-sm text-zinc-500">{{ $bookingPage->timezone }}</div>
            </div>
        </flux:card>
    @else
        <div class="grid overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 lg:grid-cols-[0.8fr_1.2fr]">
            <aside class="border-b border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-800/50 sm:p-8 lg:border-r lg:border-b-0">
                <div class="flex size-12 items-center justify-center rounded-full bg-zinc-900 text-lg font-semibold text-white dark:bg-white dark:text-zinc-900">
                    {{ mb_strtoupper(mb_substr($bookingPage->user->name, 0, 1)) }}
                </div>
                <flux:text variant="subtle" class="mt-5">{{ $bookingPage->user->name }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $bookingPage->title }}</flux:heading>
                @if ($bookingPage->description)
                    <flux:text class="mt-3">{{ $bookingPage->description }}</flux:text>
                @endif

                <div class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                    <div class="flex items-center gap-3">
                        <flux:icon.clock class="size-5 text-zinc-400" />
                        {{ $bookingPage->duration_minutes }} minutes
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:icon.globe-alt class="size-5 text-zinc-400" />
                        {{ $bookingPage->timezone }}
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:icon.calendar-days class="size-5 text-zinc-400" />
                        Google Calendar invitation
                    </div>
                </div>
            </aside>

            <main class="p-6 sm:p-8">
                <form wire:submit="book" class="space-y-7">
                    <flux:input
                        wire:model.live="selectedDate"
                        type="date"
                        label="Choose a date"
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

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="guestName" label="Your name" autocomplete="name" />
                        <flux:input wire:model="guestEmail" type="email" label="Email" autocomplete="email" />
                    </div>

                    <flux:select wire:model="guestTimezone" label="Your timezone" variant="listbox" searchable>
                        @foreach ($timezones as $zone)
                            <flux:select.option :value="$zone">{{ $zone }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input
                        wire:model="meetingTitle"
                        label="What's this meeting about?"
                        placeholder="{{ $bookingPage->title }}"
                        description="Optional. Becomes the title on both calendars."
                    />

                    <flux:textarea
                        wire:model="notes"
                        label="Description"
                        rows="3"
                        placeholder="Anything that would help prepare?"
                    />

                    <flux:button type="submit" variant="primary" class="w-full!" wire:loading.attr="disabled" wire:target="book">
                        <span wire:loading.remove wire:target="book">Request meeting</span>
                        <span wire:loading wire:target="book">Adding to calendar…</span>
                    </flux:button>
                </form>
            </main>
        </div>
    @endif
</div>
