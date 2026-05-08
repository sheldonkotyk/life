@props(['title' => 'Life'])
<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-50 dark:bg-zinc-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    @fluxAppearance
</head>
<body class="h-full text-zinc-800 dark:text-zinc-200">
    @auth
        @php
            $here = trim(request()->path(), '/');
            $currentUser = auth()->user();
            $userHouseholds = $currentUser->households()->orderBy('name')->get();
            $navItems = [
                'today' => ['label' => 'Today', 'icon' => 'sun'],
                'plan' => ['label' => 'Plan', 'icon' => 'calendar-days'],
                'availability' => ['label' => 'Attendance', 'icon' => 'user-group'],
                'recipes' => ['label' => 'Recipes', 'icon' => 'book-open'],
                'shopping' => ['label' => 'Shopping', 'icon' => 'shopping-cart'],
                'tracker' => ['label' => 'Tracker', 'icon' => 'chart-bar'],
            ];
        @endphp

        <flux:sidebar stashable sticky class="lg:hidden bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.header>
                <flux:sidebar.brand href="{{ url('/today') }}" name="Life" />
            </flux:sidebar.header>

            <flux:navlist>
                @foreach ($navItems as $path => $item)
                    <flux:navlist.item href="{{ url('/' . $path) }}" icon="{{ $item['icon'] }}" :current="$here === $path">{{ $item['label'] }}</flux:navlist.item>
                @endforeach
            </flux:navlist>

            <flux:sidebar.group :heading="$currentUser->household->name ?? 'Household'">
                <flux:sidebar.item icon="user-circle" :href="url('/profile')" :current="$here === 'profile'">Profile</flux:sidebar.item>
                @if ($currentUser->household)
                    <flux:sidebar.item icon="home" :href="url('/household')" :current="$here === 'household'">Household</flux:sidebar.item>
                @endif
                <form method="POST" action="{{ url('/logout') }}">@csrf
                    <flux:sidebar.item icon="arrow-right-start-on-rectangle" as="button" type="submit">Sign out</flux:sidebar.item>
                </form>
            </flux:sidebar.group>

            @if ($userHouseholds->count() > 1)
                <flux:sidebar.group heading="Switch household">
                    @foreach ($userHouseholds as $h)
                        @if ($h->id === $currentUser->household_id)
                            <flux:sidebar.item icon="check">{{ $h->name }}</flux:sidebar.item>
                        @else
                            <form method="POST" action="{{ route('household.switch', $h) }}">@csrf
                                <flux:sidebar.item as="button" type="submit" icon="arrow-right-circle">{{ $h->name }}</flux:sidebar.item>
                            </form>
                        @endif
                    @endforeach
                </flux:sidebar.group>
            @endif

            <flux:sidebar.group heading="Appearance"
                x-data="{ get value() { return $flux.appearance }, set value(v) { $flux.appearance = v } }"
            >
                <flux:sidebar.item icon="sun" as="button" type="button" x-on:click="value = 'light'" ::data-current="value === 'light'">Light</flux:sidebar.item>
                <flux:sidebar.item icon="moon" as="button" type="button" x-on:click="value = 'dark'" ::data-current="value === 'dark'">Dark</flux:sidebar.item>
                <flux:sidebar.item icon="computer-desktop" as="button" type="button" x-on:click="value = 'system'" ::data-current="value === 'system'">System</flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar>

        <flux:header sticky class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 px-4 lg:px-8">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:brand href="{{ url('/today') }}" name="Life" class="!me-6" />

            <flux:navbar class="-mb-px hidden lg:flex">
                @foreach ($navItems as $path => $item)
                    <flux:navbar.item href="{{ url('/' . $path) }}" :current="$here === $path">{{ $item['label'] }}</flux:navbar.item>
                @endforeach
            </flux:navbar>

            <flux:spacer />

            <flux:dropdown position="bottom" align="end" class="hidden lg:flex">
                <flux:button size="sm" variant="ghost">
                    <flux:icon icon="sun" class="dark:hidden!" />
                    <flux:icon icon="moon" class="hidden! dark:block!" />
                </flux:button>
                <flux:menu>
                    <flux:menu.radio.group
                        x-data="{ get value() { return $flux.appearance }, set value(v) { $flux.appearance = v } }"
                        x-model="value"
                    >
                        <flux:menu.radio value="light" icon="sun">Light</flux:menu.radio>
                        <flux:menu.radio value="dark" icon="moon">Dark</flux:menu.radio>
                        <flux:menu.radio value="system" icon="computer-desktop">System</flux:menu.radio>
                    </flux:menu.radio.group>
                </flux:menu>
            </flux:dropdown>

            <flux:dropdown position="bottom" align="end" class="hidden lg:flex">
                <flux:profile
                    name="{{ $currentUser->name }}"
                    initials="{{ strtoupper(mb_substr($currentUser->name, 0, 1)) }}"
                    avatar="{{ $currentUser->avatar }}"
                    circle
                />
                <flux:menu>
                    <flux:menu.group :heading="$currentUser->household->name ?? 'Household'">
                        <flux:menu.item icon="user-circle" :href="url('/profile')">Profile</flux:menu.item>
                        @if ($currentUser->household)
                            <flux:menu.item icon="home" :href="url('/household')">Household</flux:menu.item>
                        @endif
                        <form method="POST" action="{{ url('/logout') }}">@csrf
                            <flux:menu.item icon="arrow-right-start-on-rectangle" as="button" type="submit">Sign out</flux:menu.item>
                        </form>
                    </flux:menu.group>

                    @if ($userHouseholds->count() > 1)
                        <flux:menu.separator />
                        <flux:menu.group heading="Switch household">
                            @foreach ($userHouseholds as $h)
                                @if ($h->id === $currentUser->household_id)
                                    <flux:menu.item icon="check">{{ $h->name }}</flux:menu.item>
                                @else
                                    <form method="POST" action="{{ route('household.switch', $h) }}">@csrf
                                        <flux:menu.item as="button" type="submit" icon="arrow-right-circle">{{ $h->name }}</flux:menu.item>
                                    </form>
                                @endif
                            @endforeach
                        </flux:menu.group>
                    @endif
                </flux:menu>
            </flux:dropdown>
        </flux:header>
    @endauth

    <flux:main container>
        @if (session('status'))
            <flux:callout color="emerald" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
        @endif
        {{ $slot }}
    </flux:main>

    @fluxScripts

    @auth
    @if (! auth()->user()->timezone)
        <script>
        (function () {
            try {
                var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (!tz) return;
                fetch('{{ url('/me/timezone') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ timezone: tz }),
                });
            } catch (e) {}
        })();
        </script>
    @endif
    @endauth
</body>
</html>
