{{-- Phone device frame containing a stacked day list --}}
<div class="card-shadow rounded-[2.5rem] p-2 bg-zinc-900 border border-zinc-800 w-[260px]">
    <div class="rounded-[2rem] overflow-hidden bg-white dark:bg-zinc-900">
        {{-- Status bar --}}
        <div class="px-5 pt-2 pb-1 flex items-center justify-between text-[10px] text-zinc-500">
            <span>9:41</span>
            <span class="flex items-center gap-1">
                <span class="w-3 h-1.5 rounded-sm bg-zinc-300 dark:bg-zinc-600"></span>
                <span class="w-3 h-1.5 rounded-sm bg-zinc-300 dark:bg-zinc-600"></span>
                <span class="w-4 h-1.5 rounded-sm bg-zinc-400 dark:bg-zinc-500"></span>
            </span>
        </div>

        {{-- App header --}}
        <div class="px-4 py-2 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-1.5 text-sm font-semibold">
                <span class="text-indigo-600">✦</span>
                <span>Life</span>
            </div>
            <div class="w-6 h-6 rounded-full bg-indigo-400"></div>
        </div>

        {{-- Title --}}
        <div class="px-4 pt-3 pb-2">
            <div class="text-sm font-semibold">Weekly Plan</div>
            <div class="text-[10px] text-zinc-500">Mar 3 – Mar 9</div>
        </div>

        {{-- Day cards --}}
        <div class="px-3 pb-3 space-y-2">
            @php
                $days = [
                    ['day' => 'Wednesday', 'date' => 'Mar 5', 'today' => true,  'meals' => [['B','Yogurt bowls'], ['L','Leftover pasta'], ['D','Sheet-pan tacos']]],
                    ['day' => 'Thursday',  'date' => 'Mar 6', 'today' => false, 'meals' => [['B','Toast & eggs'], ['D','Coconut curry']]],
                ];
            @endphp
            @foreach ($days as $d)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                    <div class="px-3 py-2 flex items-center justify-between {{ $d['today'] ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                        <span class="text-xs font-semibold {{ $d['today'] ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d['day'] }}</span>
                        <span class="text-[10px] text-zinc-500">{{ $d['date'] }}</span>
                    </div>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($d['meals'] as [$slot, $name])
                            <div class="px-3 py-2">
                                <div class="text-[9px] uppercase tracking-wide text-zinc-500">
                                    {{ ['B' => 'Breakfast','L' => 'Lunch','D' => 'Dinner'][$slot] }}
                                </div>
                                <div class="text-xs font-semibold mt-0.5">{{ $name }}</div>
                                <div class="flex gap-0.5 mt-1">
                                    <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                                    <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tab bar --}}
        <div class="border-t border-zinc-200 dark:border-zinc-800 grid grid-cols-5 text-[9px] text-zinc-500">
            @foreach (['Plan','Att.','Fam.','Rec.','Shop'] as $i => $t)
                <div class="py-2 text-center {{ $i === 0 ? 'text-indigo-600 font-semibold' : '' }}">{{ $t }}</div>
            @endforeach
        </div>
    </div>
</div>
