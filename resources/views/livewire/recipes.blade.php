<div class="space-y-6">
    <div class="flex items-baseline justify-between gap-3">
        <flux:heading size="xl">Recipes</flux:heading>
        <div class="flex gap-2 items-center">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search…" icon="magnifying-glass" size="sm" />
            <flux:button :href="url('/recipes/browse')" variant="ghost" icon="globe-alt" size="sm">Browse catalog</flux:button>
            <flux:button wire:click="startCreate" variant="primary" icon="plus">New recipe</flux:button>
        </div>
    </div>

    @if ($showForm)
        <flux:card class="space-y-4">
            <div class="flex justify-between items-baseline">
                <flux:heading>{{ $editingId ? 'Edit recipe' : 'New recipe' }}</flux:heading>
                <flux:button size="sm" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                <div class="sm:col-span-3">
                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input wire:model="name" />
                        <flux:error name="name" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>Servings</flux:label>
                    <flux:input type="number" wire:model="servings" min="1" />
                </flux:field>
                <flux:field>
                    <flux:label>Prep (min)</flux:label>
                    <flux:input type="number" wire:model="prepMinutes" min="0" />
                </flux:field>
                <flux:field>
                    <flux:label>Source URL</flux:label>
                    <flux:input wire:model="sourceUrl" placeholder="https://…" />
                </flux:field>
                <div class="sm:col-span-6">
                    <flux:field>
                        <flux:label>Description</flux:label>
                        <flux:input wire:model="description" />
                    </flux:field>
                </div>
                <div class="sm:col-span-6">
                    <flux:field>
                        <flux:label>Instructions</flux:label>
                        <flux:textarea wire:model="instructions" rows="4" />
                    </flux:field>
                </div>
                <div class="sm:col-span-6">
                    <flux:callout color="amber" icon="archive-box">
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:checkbox wire:model.live="makesLeftovers" id="ml" label="Makes leftovers" />
                            @if ($makesLeftovers)
                                <div class="ml-auto flex items-center gap-2">
                                    <flux:label>Typical leftover servings</flux:label>
                                    <flux:input type="number" wire:model="defaultLeftoverServings" min="0" class:input="w-20" />
                                </div>
                            @endif
                        </div>
                    </flux:callout>
                </div>
            </div>

            {{-- Ingredients --}}
            <div>
                <div class="flex justify-between items-center mb-2">
                    <flux:subheading>Ingredients</flux:subheading>
                    <flux:button size="xs" variant="ghost" icon="plus" wire:click="addIngredientRow">Add row</flux:button>
                </div>
                <div class="space-y-3">
                    @foreach ($ingredients as $i => $ing)
                        <div class="space-y-1.5 pb-2 border-b border-zinc-100 last:border-0" wire:key="ing-{{ $i }}">
                            <div class="grid grid-cols-12 gap-2">
                                <div class="col-span-2"><flux:input size="sm" wire:model="ingredients.{{ $i }}.quantity" placeholder="Qty" /></div>
                                <div class="col-span-2"><flux:input size="sm" wire:model="ingredients.{{ $i }}.unit" placeholder="Unit" /></div>
                                <div class="col-span-4"><flux:input size="sm" wire:model="ingredients.{{ $i }}.name" placeholder="Ingredient" /></div>
                                <div class="col-span-3"><flux:input size="sm" wire:model="ingredients.{{ $i }}.category" placeholder="Category" /></div>
                                <div class="col-span-1 flex items-center justify-center">
                                    <flux:button size="xs" variant="subtle" icon="x-mark" wire:click="removeIngredientRow({{ $i }})" />
                                </div>
                            </div>
                            <div class="grid grid-cols-12 gap-2 pl-1">
                                <div class="col-span-3"><flux:input size="sm" type="number" step="0.1" min="0" wire:model.live.debounce.500ms="ingredients.{{ $i }}.calories" placeholder="kcal" /></div>
                                <div class="col-span-3"><flux:input size="sm" type="number" step="0.1" min="0" wire:model.live.debounce.500ms="ingredients.{{ $i }}.protein_g" placeholder="Protein (g)" /></div>
                                <div class="col-span-3"><flux:input size="sm" type="number" step="0.1" min="0" wire:model.live.debounce.500ms="ingredients.{{ $i }}.carbs_g" placeholder="Carbs (g)" /></div>
                                <div class="col-span-3"><flux:input size="sm" type="number" step="0.1" min="0" wire:model.live.debounce.500ms="ingredients.{{ $i }}.fat_g" placeholder="Fat (g)" /></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @php
                    $totals = ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];
                    foreach ($ingredients as $ing) {
                        foreach ($totals as $k => $_) $totals[$k] += (float) ($ing[$k] ?? 0);
                    }
                    $perServ = max(1, (int) $servings);
                @endphp
                <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach ([
                        ['label' => 'Calories', 'total' => round($totals['calories']), 'per' => round($totals['calories']/$perServ), 'unit' => ''],
                        ['label' => 'Protein', 'total' => round($totals['protein_g'], 1), 'per' => round($totals['protein_g']/$perServ, 1), 'unit' => 'g'],
                        ['label' => 'Carbs', 'total' => round($totals['carbs_g'], 1), 'per' => round($totals['carbs_g']/$perServ, 1), 'unit' => 'g'],
                        ['label' => 'Fat', 'total' => round($totals['fat_g'], 1), 'per' => round($totals['fat_g']/$perServ, 1), 'unit' => 'g'],
                    ] as $stat)
                        <div class="bg-zinc-50 dark:bg-zinc-800 rounded-md p-2 border border-zinc-200 dark:border-zinc-700">
                            <flux:text size="xs" variant="subtle">{{ $stat['label'] }}</flux:text>
                            <div class="text-sm font-semibold">{{ $stat['total'] }}{{ $stat['unit'] }} <span class="text-zinc-400 font-normal">/ {{ $stat['per'] }}{{ $stat['unit'] }} per serving</span></div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Ratings --}}
            <div>
                <flux:subheading class="mb-2">Who likes it?</flux:subheading>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach ($members as $m)
                        <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800 rounded-md px-3 py-2 border border-zinc-200 dark:border-zinc-700">
                            <div class="flex items-center gap-2">
                                <x-avatar :member="$m" size="sm" />
                                <span class="text-sm">{{ $m->name }}</span>
                            </div>
                            <div class="flex gap-1">
                                @foreach (['love' => '❤️', 'ok' => '😐', 'dislike' => '👎'] as $val => $emoji)
                                    <button type="button" wire:click="$set('ratings.{{ $m->id }}', '{{ $val }}')"
                                            class="px-2 py-0.5 rounded text-sm {{ ($ratings[$m->id] ?? null) === $val ? 'bg-indigo-100 dark:bg-indigo-900 ring-1 ring-indigo-400' : 'hover:bg-zinc-200 dark:hover:bg-zinc-700' }}">
                                        {{ $emoji }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <flux:separator />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="save">{{ $editingId ? 'Update recipe' : 'Save recipe' }}</flux:button>
            </div>
        </flux:card>
    @endif

    {{-- List --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($recipes as $r)
            <flux:card class="flex flex-col">
                <div class="flex justify-between items-start gap-2">
                    <div class="min-w-0">
                        <flux:heading size="lg" class="truncate">{{ $r->name }}</flux:heading>
                        <flux:text size="sm" variant="subtle">
                            {{ $r->servings }} servings
                            @if ($r->prep_minutes) · {{ $r->prep_minutes }} min @endif
                            @if ($r->makes_leftovers) · 🥡 leftovers @endif
                        </flux:text>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <flux:button size="xs" variant="ghost" wire:click="edit({{ $r->id }})">Edit</flux:button>
                        <flux:button size="xs" variant="subtle" icon="x-mark" wire:click="delete({{ $r->id }})" wire:confirm="Delete this recipe?" />
                    </div>
                </div>
                @if ($r->description)
                    <flux:text size="sm" class="mt-2">{{ $r->description }}</flux:text>
                @endif
                @php $ps = $r->macrosPerServing(); @endphp
                @if ($ps['calories'] > 0 || $ps['protein_g'] > 0)
                    <div class="mt-2 flex flex-wrap gap-1">
                        <flux:badge size="sm" color="zinc">{{ round($ps['calories']) }} kcal</flux:badge>
                        <flux:badge size="sm" color="zinc">P {{ $ps['protein_g'] }}g</flux:badge>
                        <flux:badge size="sm" color="zinc">C {{ $ps['carbs_g'] }}g</flux:badge>
                        <flux:badge size="sm" color="zinc">F {{ $ps['fat_g'] }}g</flux:badge>
                        <flux:text size="xs" variant="subtle" class="self-center">per serving</flux:text>
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach ($r->ratings as $rt)
                        <span class="text-xs inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full pl-0.5 pr-2 py-0.5">
                            <x-avatar :member="$rt->familyMember" size="sm" />
                            {{ $rt->familyMember->name }}
                            {!! ['love' => '❤️', 'ok' => '😐', 'dislike' => '👎'][$rt->rating] !!}
                        </span>
                    @endforeach
                </div>
            </flux:card>
        @empty
            <div class="col-span-full text-center py-12">
                <flux:text variant="subtle">No recipes yet.</flux:text>
                <flux:button class="mt-2" variant="primary" wire:click="startCreate">Add the first one</flux:button>
            </div>
        @endforelse
    </div>
</div>
