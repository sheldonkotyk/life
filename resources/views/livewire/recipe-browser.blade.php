<div class="space-y-6">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <flux:heading size="xl">Browse recipes</flux:heading>
            <flux:text size="sm" variant="subtle">Search the TheMealDB global catalog and save to your household's recipes.</flux:text>
        </div>
        <flux:button :href="url('/recipes')" variant="ghost" size="sm" icon="arrow-left">My recipes</flux:button>
    </div>

    <flux:card class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-6">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name, ingredient, area…" icon="magnifying-glass" />
            </div>
            <div class="sm:col-span-3">
                <flux:select wire:model.live="category" placeholder="All categories">
                    <flux:select.option value="">All categories</flux:select.option>
                    @foreach ($categories as $c)
                        <flux:select.option value="{{ $c }}">{{ $c }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="sm:col-span-3">
                <flux:select wire:model.live="area" placeholder="All areas">
                    <flux:select.option value="">All areas</flux:select.option>
                    @foreach ($areas as $a)
                        <flux:select.option value="{{ $a }}">{{ $a }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <flux:separator />

        <div>
            <flux:label>What's in your fridge?</flux:label>
            <flux:text size="sm" variant="subtle" class="mb-2">Add ingredients to find recipes that use all of them.</flux:text>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($ingredients as $ing)
                    <flux:badge color="emerald">
                        {{ $ing }}
                        <button type="button" wire:click="removeIngredient(@js($ing))" class="ml-1 text-emerald-700 hover:text-emerald-900">×</button>
                    </flux:badge>
                @endforeach
                <form wire:submit.prevent="addIngredient" class="flex gap-2 items-center">
                    <flux:input wire:model="ingredientInput" placeholder="e.g. chicken" size="sm" class:input="w-40" />
                    <flux:button type="submit" size="xs" variant="ghost" icon="plus">Add</flux:button>
                </form>
                @if (! empty($ingredients))
                    <flux:button size="xs" variant="ghost" icon="globe-alt" wire:click="discoverOnline" wire:loading.attr="disabled" wire:target="discoverOnline">
                        <span wire:loading.remove wire:target="discoverOnline">Discover more from TheMealDB</span>
                        <span wire:loading wire:target="discoverOnline">Searching…</span>
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>

    @if (! empty($discovered))
        <flux:card class="space-y-3">
            <div class="flex items-baseline justify-between">
                <flux:heading size="sm">Found {{ count($discovered) }} more on TheMealDB</flux:heading>
                <flux:button size="xs" variant="ghost" wire:click="$set('discovered', [])">Hide</flux:button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach ($discovered as $stub)
                    <div class="flex flex-col gap-1">
                        @if (! empty($stub['strMealThumb']))
                            <img src="{{ $stub['strMealThumb'] }}" alt="" class="w-full h-24 object-cover rounded" loading="lazy">
                        @endif
                        <div class="text-xs line-clamp-2">{{ $stub['strMeal'] }}</div>
                        <flux:button size="xs" variant="primary" icon="arrow-down-tray"
                                     wire:click="importDiscovered(@js($stub['idMeal']))">Import</flux:button>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse ($recipes as $r)
            <flux:card class="flex flex-col overflow-hidden p-0">
                @if ($r->image_url)
                    <button type="button" wire:click="preview({{ $r->id }})" class="block">
                        <img src="{{ $r->image_url }}" alt="{{ $r->name }}" class="w-full h-40 object-cover" loading="lazy">
                    </button>
                @endif
                <div class="p-4 flex flex-col gap-2 flex-1">
                    <button type="button" wire:click="preview({{ $r->id }})" class="text-left">
                        <flux:heading size="md" class="line-clamp-2">{{ $r->name }}</flux:heading>
                    </button>
                    <div class="flex flex-wrap gap-1">
                        @if ($r->category)<flux:badge size="sm" color="indigo">{{ $r->category }}</flux:badge>@endif
                        @if ($r->area)<flux:badge size="sm" color="zinc">{{ $r->area }}</flux:badge>@endif
                    </div>
                    <div class="mt-auto flex gap-2 pt-2">
                        <flux:button size="xs" variant="ghost" wire:click="preview({{ $r->id }})">View</flux:button>
                        <flux:button size="xs" variant="primary" icon="plus" wire:click="importToHousehold({{ $r->id }})">Add</flux:button>
                    </div>
                </div>
            </flux:card>
        @empty
            <div class="col-span-full text-center py-16">
                <flux:text variant="subtle">No recipes match your filters.</flux:text>
                @if (! \App\Models\GlobalRecipe::query()->exists())
                    <flux:text size="sm" variant="subtle" class="mt-2">
                        The catalog is empty. Run <code>php artisan recipes:import-themealdb</code> to populate it.
                    </flux:text>
                @endif
            </div>
        @endforelse
    </div>

    <div>{{ $recipes->links() }}</div>

    @if ($preview)
        <flux:modal name="preview" wire:model.self="previewId" :show="true" @close="$wire.closePreview()" class="max-w-2xl">
            <div class="space-y-4">
                @if ($preview->image_url)
                    <img src="{{ $preview->image_url }}" alt="" class="w-full h-56 object-cover rounded">
                @endif
                <flux:heading size="lg">{{ $preview->name }}</flux:heading>
                <div class="flex flex-wrap gap-1">
                    @if ($preview->category)<flux:badge size="sm" color="indigo">{{ $preview->category }}</flux:badge>@endif
                    @if ($preview->area)<flux:badge size="sm" color="zinc">{{ $preview->area }}</flux:badge>@endif
                    @foreach (($preview->tags ?? []) as $tag)
                        <flux:badge size="sm" color="emerald">{{ $tag }}</flux:badge>
                    @endforeach
                </div>

                <div>
                    <flux:heading size="sm">Ingredients</flux:heading>
                    <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1 text-sm">
                        @foreach ($preview->ingredients as $ing)
                            <li>• {{ trim(($ing->measure ?? '') . ' ' . $ing->name) }}</li>
                        @endforeach
                    </ul>
                </div>

                @if ($preview->instructions)
                    <div>
                        <flux:heading size="sm">Instructions</flux:heading>
                        <p class="mt-2 text-sm whitespace-pre-line">{{ $preview->instructions }}</p>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-2">
                    @if ($preview->youtube_url)
                        <flux:button :href="$preview->youtube_url" target="_blank" variant="ghost" size="sm" icon="play">YouTube</flux:button>
                    @endif
                    <flux:button variant="ghost" wire:click="closePreview">Close</flux:button>
                    <flux:button variant="primary" icon="plus" wire:click="importToHousehold({{ $preview->id }})">Add to my recipes</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
