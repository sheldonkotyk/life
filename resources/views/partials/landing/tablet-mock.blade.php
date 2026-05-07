{{-- Tablet device frame containing a weekly grid --}}
<div class="card-shadow mx-auto max-w-2xl rounded-[2rem] p-3 bg-zinc-900 border border-zinc-800">
    <div class="rounded-[1.5rem] overflow-hidden bg-white dark:bg-zinc-900">
        {{-- Faux app header --}}
        <div class="flex items-center gap-3 px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50">
            <span class="text-indigo-600 text-sm">✦</span>
            <span class="text-xs font-semibold">Life</span>
            <div class="hidden md:flex items-center gap-3 ml-2 text-[10px] text-zinc-500">
                <span class="text-zinc-900 dark:text-zinc-100 font-semibold">Plan</span>
                <span>Attendance</span>
                <span>Family</span>
                <span>Recipes</span>
                <span>Shopping</span>
                <span>Tracker</span>
            </div>
            <div class="ml-auto w-5 h-5 rounded-full bg-indigo-400"></div>
        </div>

        {{-- Toolbar --}}
        <div class="flex items-center justify-between px-4 py-3">
            <div class="text-sm font-semibold">Weekly Plan</div>
            <div class="flex items-center gap-1 text-[10px] text-zinc-500">
                <span class="px-2 py-1 rounded bg-zinc-100 dark:bg-zinc-800">‹</span>
                <span class="px-2 py-1 rounded bg-zinc-100 dark:bg-zinc-800">Today</span>
                <span class="px-2 py-1 rounded bg-zinc-100 dark:bg-zinc-800">›</span>
                <span class="ml-2">Mar 3 – Mar 9, 2026</span>
            </div>
        </div>

        {{-- Day headers --}}
        <div class="grid grid-cols-[60px_repeat(7,1fr)] text-[10px] font-semibold text-zinc-500 px-3">
            <div></div>
            @foreach ([['Mon',3],['Tue',4],['Wed',5],['Thu',6],['Fri',7],['Sat',8],['Sun',9]] as $i => [$d, $n])
                <div class="px-1.5 py-1 {{ $i === 2 ? 'text-indigo-600' : '' }}">
                    <div>{{ $d }}</div>
                    <div class="text-zinc-400 font-normal">Mar {{ $n }}</div>
                </div>
            @endforeach
        </div>

        {{-- Grid --}}
        <div class="px-3 pb-3">
            @php
                $rows = [
                    ['label' => 'Breakfast', 'items' => ['Oats','Toast','Yogurt','Eggs','Cereal','Pancakes','Bagels']],
                    ['label' => 'Lunch',     'items' => ['Wraps','Soup','Salad','Leftovers','Sandwich','Bowls','Pizza']],
                    ['label' => 'Dinner',    'items' => ['Pasta','Stir fry','Tacos','Curry','Sheet pan','Roast','Pizza']],
                ];
            @endphp
            @foreach ($rows as $r)
                <div class="grid grid-cols-[60px_repeat(7,1fr)] gap-1.5 mb-1.5">
                    <div class="text-[10px] font-semibold text-zinc-500 self-center px-1">{{ $r['label'] }}</div>
                    @foreach ($r['items'] as $i => $item)
                        <div class="rounded-md p-1.5 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-100 dark:border-zinc-700/50 min-h-[54px]">
                            <div class="flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full {{ ['bg-amber-400','bg-rose-400','bg-emerald-400','bg-indigo-400','bg-sky-400','bg-violet-400','bg-orange-400'][$i] }}"></span>
                                <span class="text-[9px] font-medium truncate">{{ $item }}</span>
                            </div>
                            <div class="text-[8px] text-zinc-400 mt-0.5">{{ 240 + $i * 35 }} kcal</div>
                            <div class="mt-1 flex gap-0.5">
                                @for ($a = 0; $a < (($i + 1) % 3 + 1); $a++)
                                    <span class="w-1.5 h-1.5 rounded-full {{ ['bg-indigo-400','bg-rose-400','bg-emerald-400','bg-amber-400'][$a] }}"></span>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
