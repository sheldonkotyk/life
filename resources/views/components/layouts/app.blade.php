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
        @php $here = trim(request()->path(), '/'); @endphp
        <flux:header sticky class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 px-4 lg:px-8">
            <flux:brand href="{{ url('/') }}" name="Life" class="!me-6">
                <x-slot name="logo">
                    <span class="text-xl">✦</span>
                </x-slot>
            </flux:brand>

            <flux:navbar class="-mb-px">
                @foreach ([
                    '' => 'Plan',
                    'availability' => 'Attendance',
                    'family' => 'Family',
                    'recipes' => 'Recipes',
                    'shopping' => 'Shopping',
                    'tracker' => 'Tracker',
                ] as $path => $label)
                    <flux:navbar.item href="{{ url('/' . $path) }}" :current="$here === $path">{{ $label }}</flux:navbar.item>
                @endforeach
            </flux:navbar>

            <flux:spacer />

            <flux:dropdown position="bottom" align="end">
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

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    name="{{ auth()->user()->name }}"
                    initials="{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}"
                    avatar="{{ auth()->user()->avatar }}"
                />
                <flux:menu>
                    <flux:menu.group :heading="auth()->user()->household->name ?? 'Household'">
                        <form method="POST" action="{{ url('/logout') }}">@csrf
                            <flux:menu.item icon="arrow-right-start-on-rectangle" as="button" type="submit">Sign out</flux:menu.item>
                        </form>
                    </flux:menu.group>
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
</body>
</html>
