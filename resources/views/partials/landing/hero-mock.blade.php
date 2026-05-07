{{-- Stylized hero mockup: a tablet-style weekly grid card with a phone overlapping --}}
<div class="relative">
    {{-- Tablet-style card --}}
    <div class="relative card-shadow rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 rotate-[-1deg]">
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between bg-zinc-50 dark:bg-zinc-800/50">
            <div class="flex items-center gap-2 text-sm font-semibold">
                <span class="text-indigo-600">✦</span>
                <span>Weekly Plan</span>
            </div>
            <div class="flex gap-1">
                <span class="w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                <span class="w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                <span class="w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
            </div>
        </div>
        <div class="grid grid-cols-7 text-[10px] font-semibold text-zinc-500 px-2 pt-2">
            @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $i => $d)
                <div class="px-1.5 py-1 {{ $i === 2 ? 'text-indigo-600' : '' }}">{{ $d }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7 gap-1.5 p-2">
            @php
                $cells = [
                    ['dot' => 'bg-amber-400', 'title' => 'Oats'],
                    ['dot' => 'bg-rose-400', 'title' => 'Pasta'],
                    ['dot' => 'bg-emerald-400', 'title' => 'Soup'],
                    ['dot' => 'bg-indigo-400', 'title' => 'Tacos'],
                    ['dot' => 'bg-sky-400', 'title' => 'Curry'],
                    ['dot' => 'bg-violet-400', 'title' => 'Pizza'],
                    ['dot' => 'bg-amber-400', 'title' => 'Roast'],
                ];
            @endphp
            @for ($row = 0; $row < 3; $row++)
                @foreach ($cells as $i => $c)
                    <div class="rounded-md p-1.5 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-100 dark:border-zinc-700/50 min-h-[42px]">
                        <div class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full {{ $c['dot'] }}"></span>
                            <span class="text-[9px] font-medium truncate text-zinc-700 dark:text-zinc-200">{{ $c['title'] }}</span>
                        </div>
                        <div class="mt-1 flex -space-x-0.5">
                            @for ($a = 0; $a < (($i + $row) % 3 + 1); $a++)
                                <span class="w-2 h-2 rounded-full ring-1 ring-white dark:ring-zinc-900 {{ ['bg-indigo-400','bg-rose-400','bg-emerald-400','bg-amber-400'][$a % 4] }}"></span>
                            @endfor
                        </div>
                    </div>
                @endforeach
            @endfor
        </div>
    </div>

    {{-- Floating phone --}}
    <div class="hidden sm:block absolute -bottom-10 -left-6 lg:-left-10 rotate-[6deg]">
        <div class="card-shadow rounded-[2rem] p-1.5 bg-zinc-900 border border-zinc-800 w-[180px]">
            <div class="rounded-[1.6rem] overflow-hidden bg-white dark:bg-zinc-900">
                <div class="px-3 py-2 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="text-[10px] font-semibold">Wednesday</div>
                    <div class="text-[8px] text-zinc-500">Mar 5</div>
                </div>
                <div class="p-2 space-y-1.5">
                    @foreach (['Breakfast','Lunch','Dinner'] as $i => $slot)
                        <div class="rounded-md bg-zinc-50 dark:bg-zinc-800 p-2">
                            <div class="text-[8px] uppercase tracking-wide text-zinc-500">{{ $slot }}</div>
                            <div class="text-[10px] font-semibold mt-0.5">{{ ['Yogurt bowls','Leftover pasta','Sheet-pan tacos'][$i] }}</div>
                            <div class="flex gap-0.5 mt-1">
                                @for ($a = 0; $a < $i + 2; $a++)
                                    <span class="w-2 h-2 rounded-full {{ ['bg-indigo-400','bg-rose-400','bg-emerald-400','bg-amber-400'][$a % 4] }}"></span>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Floating attendance chip --}}
    <div class="hidden md:block absolute -top-4 -right-2 lg:-right-6 rotate-[3deg]">
        <div class="card-shadow rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-xs">
            <div class="font-semibold flex items-center gap-1">
                <span class="text-emerald-500">✓</span> 4 in for dinner
            </div>
            <div class="flex -space-x-1 mt-1.5">
                <span class="w-5 h-5 rounded-full ring-2 ring-white dark:ring-zinc-900 bg-indigo-400"></span>
                <span class="w-5 h-5 rounded-full ring-2 ring-white dark:ring-zinc-900 bg-rose-400"></span>
                <span class="w-5 h-5 rounded-full ring-2 ring-white dark:ring-zinc-900 bg-emerald-400"></span>
                <span class="w-5 h-5 rounded-full ring-2 ring-white dark:ring-zinc-900 bg-amber-400"></span>
            </div>
        </div>
    </div>
</div>
