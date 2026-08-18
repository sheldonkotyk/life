@props(['name', 'event', 'timezone'])

@php
    $startsAt = \Carbon\CarbonImmutable::parse($event['starts_at'])->setTimezone($timezone);
    $endsAt = \Carbon\CarbonImmutable::parse($event['ends_at'])->setTimezone($timezone);
    $guests = collect($event['attendees'] ?? [])->reject(fn ($a) => $a['self'] ?? false)->values();
    // Not meetings: context Google keeps on the calendar.
    $kind = match ($event['type'] ?? 'default') {
        'workingLocation' => 'Working location',
        'focusTime' => 'Focus time',
        'outOfOffice' => 'Out of office',
        default => null,
    };
    $spansDays = ! $startsAt->isSameDay($event['all_day'] ? $endsAt->subDay() : $endsAt);
@endphp

<flux:modal :name="$name" class="md:w-[32rem]">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ $event['title'] }}</flux:heading>
            @if ($kind)
                <flux:badge size="sm" color="zinc" class="mt-2">{{ $kind }}</flux:badge>
            @endif
            <flux:text variant="subtle" class="mt-1">
                @if ($event['all_day'] && $spansDays)
                    All day · {{ $startsAt->format('F j') }} – {{ $endsAt->subDay()->format('F j') }}
                @elseif ($event['all_day'])
                    All day · {{ $startsAt->format('l, F j') }}
                @elseif ($spansDays)
                    {{ $startsAt->format('l, F j · g:i A') }} – {{ $endsAt->format('l, F j · g:i A') }}
                @else
                    {{ $startsAt->format('l, F j · g:i A') }} – {{ $endsAt->format('g:i A') }}
                @endif
            </flux:text>
            <flux:text variant="subtle" class="mt-1">
                {{ $event['calendar_name'] === $event['account'] ? $event['account'] : $event['calendar_name'].' · '.$event['account'] }}
            </flux:text>
        </div>

        @if ($event['location'] ?? null)
            <div class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                <flux:icon.map-pin class="size-4 shrink-0 text-zinc-400" />
                <span class="break-words">{{ $event['location'] }}</span>
            </div>
        @endif

        @if ($guests->isNotEmpty())
            <div>
                <flux:label>{{ $guests->count() }} {{ Str::plural('guest', $guests->count()) }}</flux:label>
                <div class="mt-2 space-y-2">
                    @foreach ($guests as $guest)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <div class="truncate text-zinc-900 dark:text-white">
                                    {{ $guest['name'] }}
                                    @if ($guest['organizer'])
                                        <span class="text-xs text-zinc-500">· organiser</span>
                                    @endif
                                </div>
                                @if ($guest['name'] !== $guest['email'])
                                    <div class="truncate text-xs text-zinc-500">{{ $guest['email'] }}</div>
                                @endif
                            </div>
                            <flux:badge size="sm" :color="match ($guest['status']) {
                                'accepted' => 'emerald',
                                'declined' => 'red',
                                'tentative' => 'amber',
                                default => 'zinc',
                            }">
                                {{ match ($guest['status']) {
                                    'accepted' => 'Going',
                                    'declined' => 'Declined',
                                    'tentative' => 'Maybe',
                                    default => 'No reply',
                                } }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (filled($event['description'] ?? null))
            <div>
                <flux:label>Description</flux:label>
                @php
                    // Google keeps html here, and calendar tools pad it with
                    // empty lines that read as a rendering fault.
                    $description = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $event['description']));
                    $description = trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode($description)));
                @endphp
                <div class="mt-2 max-h-64 overflow-y-auto text-sm whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $description }}</div>
            </div>
        @endif

        @if ($event['link'] ?? null)
            <flux:button :href="$event['link']" target="_blank" variant="ghost" icon="arrow-top-right-on-square">
                Open in Google Calendar
            </flux:button>
        @endif
    </div>
</flux:modal>
