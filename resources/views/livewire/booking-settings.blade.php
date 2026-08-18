<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">Bookings</flux:heading>
            <flux:text variant="subtle">Combine availability across Google accounts and let people book time with you.</flux:text>
        </div>

        @if ($bookingPage?->is_enabled)
            <flux:button :href="$publicUrl" target="_blank" icon="arrow-top-right-on-square">
                View booking page
            </flux:button>
        @endif
    </div>

    @error('google')
        <flux:callout color="red" icon="exclamation-triangle">{{ $message }}</flux:callout>
    @enderror

    <section class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="lg">Google accounts</flux:heading>
                <flux:text variant="subtle">
                    {{ $connections->count() }} {{ Str::plural('account', $connections->count()) }} connected. OAuth credentials are encrypted in Token Vault.
                </flux:text>
            </div>

            <div class="flex gap-2">
                @if ($connections->isNotEmpty())
                    <flux:button wire:click="refreshCalendars" wire:loading.attr="disabled" wire:target="refreshCalendars" icon="arrow-path">
                        Refresh all
                    </flux:button>
                @endif
                <flux:button :href="route('google-calendar.redirect')" variant="primary" icon="plus">
                    {{ $connections->isEmpty() ? 'Connect Google Calendar' : 'Connect another account' }}
                </flux:button>
            </div>
        </div>

        @if ($connections->isEmpty())
            <flux:card>
                <div class="flex flex-col items-center gap-4 py-6 text-center">
                    <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800">
                        <flux:icon.calendar-days class="size-6" />
                    </div>
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-white">No Google accounts connected</div>
                        <flux:text variant="subtle">Connect your first account to publish a booking page.</flux:text>
                    </div>
                </div>
            </flux:card>
        @else
            <div class="grid gap-4 lg:grid-cols-2" role="tablist">
                @foreach ($accountSummaries as $summary)
                    @php
                        $account = $summary['connection'];
                        $token = $summary['token'];
                        $accountPage = $summary['page'];
                        $isEditing = $bookingPage?->google_calendar_connection_id === $account->id;
                    @endphp
                    <div wire:key="google-account-{{ $account->id }}">
                    <flux:card
                        class="space-y-4 cursor-pointer transition {{ $isEditing
                            ? 'ring-2 ring-blue-500 dark:ring-blue-400'
                            : 'hover:ring-2 hover:ring-zinc-300 dark:hover:ring-zinc-600' }}"
                        wire:click="editAccount({{ $account->id }})"
                        wire:keydown.enter="editAccount({{ $account->id }})"
                        role="tab"
                        tabindex="0"
                        aria-selected="{{ $isEditing ? 'true' : 'false' }}"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <flux:avatar
                                    circle
                                    color="auto"
                                    color:seed="{{ $account->google_email }}"
                                    :src="$account->google_avatar_url"
                                    :name="$account->google_name ?: $account->google_email"
                                    class="shrink-0"
                                />
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $account->google_email }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        @if (! $token)
                                            <flux:badge color="red" size="sm">Reconnect required</flux:badge>
                                        @elseif (! $token->maskedRefreshToken())
                                            <flux:badge color="amber" size="sm">No refresh token</flux:badge>
                                        @else
                                            <flux:badge color="emerald" size="sm">Connected</flux:badge>
                                        @endif
                                        <span class="text-xs text-zinc-500">{{ $summary['calendar_count'] }} calendars</span>
                                    </div>
                                </div>
                            </div>

                            <flux:dropdown position="bottom" align="end" @click.stop>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" square />
                                <flux:menu>
                                    <flux:modal.trigger name="google-account-details-{{ $account->id }}">
                                        <flux:menu.item icon="information-circle">Details</flux:menu.item>
                                    </flux:modal.trigger>
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        wire:click="disconnect({{ $account->id }})"
                                        wire:confirm="Disconnect this Google account? Calendars from your other accounts will remain connected."
                                        icon="trash"
                                        variant="danger"
                                    >Disconnect</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        @if ($isEditing)
                            <flux:badge color="blue" size="sm" icon="pencil-square">Editing this page below</flux:badge>
                        @endif

                        @if (isset($accountErrors[$account->id]))
                            <flux:callout color="red" icon="exclamation-triangle">{{ $accountErrors[$account->id] }}</flux:callout>
                        @else
                            <div class="grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 p-3 text-sm dark:bg-zinc-800/60">
                                <div>
                                    <div class="text-xs text-zinc-500">Conflict calendars</div>
                                    <div class="mt-1 font-medium text-zinc-800 dark:text-zinc-200">{{ $summary['conflict_count'] }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-zinc-500">Receives bookings</div>
                                    <div class="mt-1 truncate font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $summary['destination']?->google_calendar_name ?? 'Not selected' }}
                                    </div>
                                </div>
                                <div class="col-span-2">
                                    <div class="text-xs text-zinc-500">This account's page</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        @if ($accountPage)
                                            <flux:badge :color="$accountPage->is_enabled ? 'emerald' : 'zinc'" size="sm">
                                                {{ $accountPage->is_enabled ? 'Published' : 'Draft' }}
                                            </flux:badge>
                                            <span class="truncate font-mono text-xs text-zinc-600 dark:text-zinc-300">/meet/{{ $accountPage->slug }}</span>
                                        @else
                                            <span class="text-xs text-zinc-500">Not set up yet</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </flux:card>

                    <flux:modal name="google-account-details-{{ $account->id }}" class="md:w-[34rem]">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">Google account details</flux:heading>
                                <flux:text variant="subtle">Connection metadata is visible; secret values never leave Token Vault.</flux:text>
                            </div>

                            <dl class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Account</dt>
                                    <dd class="mt-1 break-all text-sm text-zinc-900 dark:text-white">{{ $account->google_email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Google identity</dt>
                                    <dd class="mt-1 break-all font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $account->google_user_id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Connected</dt>
                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-white">{{ $account->created_at->format('M j, Y · g:i A') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Calendars found</dt>
                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-white">{{ $summary['calendar_count'] }}</dd>
                                </div>
                            </dl>

                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="font-medium text-zinc-900 dark:text-white">Vaulted OAuth credential</div>
                                    @if ($token && $token->expires_at)
                                        <flux:badge :color="$token->expires_at->isFuture() ? 'emerald' : 'red'" size="sm">
                                            {{ $token->expires_at->isFuture() ? 'Access active' : 'Access expired' }}
                                        </flux:badge>
                                    @endif
                                </div>

                                @if ($token)
                                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs text-zinc-500">Access token</dt>
                                            <dd class="mt-1 font-mono text-sm text-zinc-800 dark:text-zinc-200">{{ $token->maskedAccessToken() }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-zinc-500">Refresh token</dt>
                                            <dd class="mt-1 font-mono text-sm text-zinc-800 dark:text-zinc-200">{{ $token->maskedRefreshToken() ?? 'Not provided' }}</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs text-zinc-500">Access expires</dt>
                                            <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">
                                                {{ $token->expires_at?->format('M j, Y · g:i A') ?? 'Unknown' }}
                                                @if ($token->expires_at)
                                                    <span class="text-zinc-500">({{ $token->expires_at->diffForHumans() }})</span>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                @else
                                    <flux:callout color="red" icon="exclamation-triangle" class="mt-4">No vaulted credential is attached. Reconnect this account.</flux:callout>
                                @endif
                            </div>

                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Granted scopes</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @forelse ($summary['scopes'] as $scope)
                                        <flux:badge size="sm">{{ $scope }}</flux:badge>
                                    @empty
                                        <flux:text variant="subtle">No scope metadata recorded.</flux:text>
                                    @endforelse
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <flux:modal.close>
                                    <flux:button>Close</flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @error('calendars')
        <flux:callout color="red" icon="exclamation-triangle">{{ $message }}</flux:callout>
    @enderror

    @if ($bookingPage && $calendars !== [])
        <form wire:submit="save" class="space-y-6">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Public page</flux:heading>
                    <flux:text variant="subtle">Choose what visitors see and when the page is available.</flux:text>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <flux:input wire:model="title" label="Meeting title" />
                    <flux:input wire:model="slug" label="Public link" prefix="{{ url('/meet') }}/" />
                </div>

                <flux:textarea wire:model="description" label="Description" rows="3" placeholder="What should guests know before booking?" />

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <flux:select wire:model="durationMinutes" label="Duration">
                        @foreach ([15, 30, 45, 60, 90] as $minutes)
                            <flux:select.option :value="$minutes">{{ $minutes }} minutes</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="bufferMinutes" label="Buffer">
                        @foreach ([0, 5, 10, 15, 30] as $minutes)
                            <flux:select.option :value="$minutes">{{ $minutes === 0 ? 'None' : $minutes.' minutes' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="minimumNoticeHours" type="number" min="0" max="168" label="Minimum notice (hours)" />
                    <flux:select wire:model="timezone" label="Timezone" variant="listbox" searchable>
                        @foreach ($timezones as $zone)
                            <flux:select.option :value="$zone">{{ $zone }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </flux:card>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Availability</flux:heading>
                    <flux:text variant="subtle">A busy event on any checked calendar, across any account, hides that time.</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="availabilityStartsAt" type="time" label="Available from" />
                    <flux:input wire:model="availabilityEndsAt" type="time" label="Available until" />
                </div>

                <flux:checkbox.group wire:model="availableDays" label="Available days">
                    <div class="grid gap-3 sm:grid-cols-4 lg:grid-cols-7">
                        @foreach ($days as $number => $day)
                            <flux:checkbox :value="$number" :label="$day" />
                        @endforeach
                    </div>
                </flux:checkbox.group>

                <div class="space-y-4">
                    <div>
                        <flux:label>Calendars checked for conflicts</flux:label>
                        <flux:text size="sm" variant="subtle">Select calendars from every account that should block your availability.</flux:text>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($accountSummaries as $summary)
                            @if ($summary['calendars'] !== [])
                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div class="mb-3 truncate text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $summary['connection']->google_email }}
                                    </div>
                                    <flux:checkbox.group wire:model="availabilityCalendarKeys" class="flex-col">
                                        @foreach ($summary['calendars'] as $calendar)
                                            <flux:checkbox
                                                wire:key="availability-calendar-{{ $calendar['key'] }}"
                                                value="{{ $calendar['key'] }}"
                                                label="{{ $calendar['name'] }}{{ $calendar['primary'] ? ' (Primary)' : '' }}"
                                            />
                                        @endforeach
                                    </flux:checkbox.group>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <flux:error name="availabilityCalendarKeys" />
                </div>

                <flux:select wire:model="bookingCalendarKey" label="Calendar where new meetings are created" variant="listbox">
                    <flux:select.option value="">Choose a calendar</flux:select.option>
                    @foreach ($calendars as $calendar)
                        @if ($calendar['connection_id'] === $bookingPage->google_calendar_connection_id
                            && in_array($calendar['access_role'], ['owner', 'writer'], true))
                            <flux:select.option wire:key="booking-calendar-{{ $calendar['key'] }}" value="{{ $calendar['key'] }}">
                                {{ $calendar['name'] }} — {{ $calendar['connection_email'] }}
                            </flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
            </flux:card>

            <flux:card class="space-y-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="lg">Publish</flux:heading>
                        <flux:text variant="subtle">Share this link with anyone who needs time with you.</flux:text>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6">
                        <flux:switch wire:model="requiresApproval" label="Approve each request" />
                        <flux:switch wire:model="isEnabled" label="Accept bookings" />
                    </div>
                </div>

                {{-- Its own row: a full url needs the width. --}}
                <div class="flex items-center gap-2">
                    <flux:input readonly copyable :value="$publicUrl" class="min-w-0 flex-1" />
                    <flux:button :href="$publicUrl" target="_blank" variant="ghost" icon="arrow-top-right-on-square">
                        Open
                    </flux:button>
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                        Save booking settings
                    </flux:button>
                </div>
            </flux:card>
        </form>
    @endif

    @if ($pendingRequests->isNotEmpty())
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Requests waiting on you</flux:heading>
                <flux:text variant="subtle">These times are held. Nothing reaches your calendar until you accept.</flux:text>
            </div>

            @error('bookings')
                <flux:callout color="red" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($pendingRequests as $request)
                    <div wire:key="request-{{ $request->id }}" class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $request->guest_name }}</div>
                            <div class="text-sm text-zinc-500">{{ $request->guest_email }}</div>
                            @if ($request->guest_title)
                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $request->guest_title }}</div>
                            @endif
                            @if ($request->notes)
                                <div class="mt-1 text-sm text-zinc-500">{{ $request->notes }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 sm:justify-end">
                            <div class="text-sm text-zinc-600 dark:text-zinc-300 sm:text-right">
                                {{ $request->starts_at->setTimezone($bookingPage->timezone)->format('M j, Y · g:i A') }}
                            </div>
                            <flux:button wire:click="acceptBooking({{ $request->id }})" size="sm" variant="primary">Accept</flux:button>
                            <flux:button
                                wire:click="declineBooking({{ $request->id }})"
                                wire:confirm="Decline this request from {{ $request->guest_name }}?"
                                size="sm"
                                variant="subtle"
                            >Decline</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    @if ($upcomingBookings->isNotEmpty())
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Upcoming bookings</flux:heading>
                <flux:text variant="subtle">Meetings created through your public page.</flux:text>
            </div>

            @error('bookings')
                <flux:callout color="red" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($upcomingBookings as $upcoming)
                    <div wire:key="booking-{{ $upcoming->id }}" class="flex flex-col gap-1 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $upcoming->guest_name }}</div>
                            <div class="text-sm text-zinc-500">{{ $upcoming->guest_email }}</div>
                            @if ($upcoming->guest_title)
                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $upcoming->guest_title }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-4 sm:justify-end">
                            <div class="text-sm text-zinc-600 dark:text-zinc-300 sm:text-right">
                                {{ $upcoming->starts_at->setTimezone($bookingPage->timezone)->format('M j, Y · g:i A') }}
                                @if ($upcoming->rescheduled_at)
                                    <div class="text-xs text-zinc-500">Rescheduled</div>
                                @endif
                            </div>
                            <flux:button :href="$upcoming->rescheduleUrl()" size="sm" variant="subtle">
                                Reschedule
                            </flux:button>
                            <flux:button
                                wire:click="cancelBooking({{ $upcoming->id }})"
                                wire:confirm="Cancel this meeting with {{ $upcoming->guest_name }}?"
                                size="sm"
                                variant="subtle"
                            >
                                Cancel
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
