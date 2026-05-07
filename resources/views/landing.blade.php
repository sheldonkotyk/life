<!DOCTYPE html>
<html lang="en" class="bg-zinc-50 dark:bg-zinc-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Life — A home for everything your family is up to</title>
    <meta name="description" content="Plan meals, track attendance, share recipes, and keep your household in sync — all in one calm, family-friendly app.">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('life.appearance');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var dark = saved ? saved === 'dark' : prefersDark;
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .grad-bg {
            background:
                radial-gradient(60rem 40rem at 80% -10%, rgba(99,102,241,0.18), transparent 60%),
                radial-gradient(50rem 35rem at -10% 30%, rgba(16,185,129,0.14), transparent 60%),
                radial-gradient(40rem 30rem at 50% 110%, rgba(244,114,182,0.12), transparent 60%);
        }
        .card-shadow { box-shadow: 0 20px 60px -20px rgba(15, 23, 42, 0.25); }
        .dark .card-shadow { box-shadow: 0 20px 60px -20px rgba(0, 0, 0, 0.6); }
    </style>
</head>
<body class="text-zinc-800 dark:text-zinc-200 antialiased">

{{-- ─── Top bar ───────────────────────────────────────────────── --}}
<header class="relative z-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-lg">
            <span class="text-xl text-indigo-600 dark:text-indigo-400">✦</span>
            <span>Life</span>
        </a>
        <nav class="hidden sm:flex items-center gap-6 text-sm text-zinc-600 dark:text-zinc-400">
            <a href="#features" class="hover:text-zinc-900 dark:hover:text-zinc-100">Features</a>
            <a href="#devices" class="hover:text-zinc-900 dark:hover:text-zinc-100">On every device</a>
            <a href="#start" class="hover:text-zinc-900 dark:hover:text-zinc-100">Get started</a>
        </nav>
        <div class="flex items-center gap-2">
            <button
                type="button"
                aria-label="Toggle dark mode"
                onclick="(function(){var d=document.documentElement.classList.toggle('dark');try{localStorage.setItem('life.appearance', d?'dark':'light');}catch(e){}})()"
                class="w-9 h-9 inline-flex items-center justify-center rounded-md text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800"
            >
                <svg class="w-5 h-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                <svg class="w-5 h-5 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <a href="{{ route('login') }}" class="text-sm font-medium px-3 py-1.5 rounded-md text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800">Sign in</a>
            <a href="{{ route('login') }}" class="text-sm font-medium px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white">Get started</a>
        </div>
    </div>
</header>

{{-- ─── Hero ─────────────────────────────────────────────────── --}}
<section class="grad-bg relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-20 sm:pt-20 sm:pb-28 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div>
            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 bg-indigo-100/70 dark:bg-indigo-900/40 px-3 py-1 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                For households, big and small
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50 leading-[1.05]">
                A home for everything <br class="hidden sm:block">your family is up to.
            </h1>
            <p class="mt-5 text-lg sm:text-xl text-zinc-600 dark:text-zinc-400 max-w-xl">
                Plan meals together, track who's home for dinner, share recipes, build a smart shopping list, and keep an eye on goals — all in one calm place the whole household can use.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-sm">
                    Start your household
                    <span aria-hidden>→</span>
                </a>
                <a href="#features" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 font-semibold">
                    See what's inside
                </a>
            </div>
            <div class="mt-6 flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                <span>No password needed — sign in with email or Apple.</span>
            </div>
        </div>

        {{-- Hero device cluster --}}
        <div class="relative">
            @include('partials.landing.hero-mock')
        </div>
    </div>
</section>

{{-- ─── Features ─────────────────────────────────────────────── --}}
<section id="features" class="py-20 sm:py-28 bg-white dark:bg-zinc-900 border-y border-zinc-200 dark:border-zinc-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Everything your household actually needs.</h2>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">Each feature works on its own — but they get smarter together.</p>
        </div>

        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @php
                $features = [
                    [
                        'title' => 'Weekly meal planner',
                        'body'  => 'Drop breakfast, lunch, and dinner into a clean weekly grid. Attach recipes, see calories and macros at a glance, and save leftovers for later.',
                        'icon'  => 'calendar',
                        'tint'  => 'indigo',
                    ],
                    [
                        'title' => 'Meal attendance',
                        'body'  => 'Each family member checks which meals they\'ll be home for — so the cook knows exactly how many to plan for.',
                        'icon'  => 'check',
                        'tint'  => 'emerald',
                    ],
                    [
                        'title' => 'Family profiles',
                        'body'  => 'Names, colors, birthdays, dietary notes, and optional daily macro targets for each person — adults and kids alike.',
                        'icon'  => 'users',
                        'tint'  => 'rose',
                    ],
                    [
                        'title' => 'Recipes',
                        'body'  => 'Save your own recipes or browse a shared catalog. Search, scale servings, and pull recipes straight into the planner.',
                        'icon'  => 'book',
                        'tint'  => 'amber',
                    ],
                    [
                        'title' => 'Smart shopping list',
                        'body'  => 'Generate a shopping list from your week\'s plan. Tick things off together, even from different phones.',
                        'icon'  => 'cart',
                        'tint'  => 'sky',
                    ],
                    [
                        'title' => 'Daily tracker',
                        'body'  => 'A gentle daily log of calories and macros, lined up against each member\'s targets. No guilt, just clarity.',
                        'icon'  => 'chart',
                        'tint'  => 'violet',
                    ],
                ];
            @endphp

            @foreach ($features as $f)
                @php
                    $tint = $f['tint'];
                    $tintClasses = [
                        'indigo'  => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
                        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                        'rose'    => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                        'amber'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                        'sky'     => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
                        'violet'  => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
                    ][$tint];
                @endphp
                <div class="group p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/70 hover:border-zinc-300 dark:hover:border-zinc-600 transition">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center {{ $tintClasses }}">
                        @include('partials.landing.icon', ['name' => $f['icon']])
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ $f['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $f['body'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-14 grid md:grid-cols-3 gap-5">
            <div class="p-5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Magic-link & Apple sign-in</div>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">No passwords. Tap a link in your email or use Sign in with Apple — that's it.</p>
            </div>
            <div class="p-5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Invite the whole household</div>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Share a short invite code or link. Everyone lands in the same household automatically.</p>
            </div>
            <div class="p-5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Light & dark, your timezone</div>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Looks great in either theme, and dates and weeks line up with where you actually live.</p>
            </div>
        </div>
    </div>
</section>

{{-- ─── Devices ──────────────────────────────────────────────── --}}
<section id="devices" class="py-20 sm:py-28 grad-bg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Designed for the kitchen, the couch, and the carpool line.</h2>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">Life is fully responsive — the weekly grid spreads across desktop and tablet, while phones get a fast, day-by-day stack.</p>
        </div>

        <div class="mt-14 grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">
            {{-- Tablet --}}
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-4">Tablet — Weekly grid</div>
                @include('partials.landing.tablet-mock')
            </div>

            {{-- Phone --}}
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-4">Phone — Day stack</div>
                <div class="flex justify-center lg:justify-start">
                    @include('partials.landing.phone-mock')
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── CTA ──────────────────────────────────────────────────── --}}
<section id="start" class="py-20 sm:py-24 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Bring a little more calm to dinner time.</h2>
        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">Start your household in under a minute. Invite the family when you're ready.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-sm">
                Get started — it's free
            </a>
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 font-semibold">
                I already have an invite code
            </a>
        </div>
    </div>
</section>

<footer class="py-10 text-center text-sm text-zinc-500">
    <div class="flex items-center justify-center gap-2">
        <span class="text-indigo-500">✦</span>
        <span>Life · A calm home for your family</span>
    </div>
</footer>

</body>
</html>
