<div class="grid gap-6 md:grid-cols-[260px_1fr]">
    {{-- Preview --}}
    <div class="space-y-3">
        <div class="rounded-2xl bg-zinc-100 dark:bg-zinc-800 p-3 sticky top-4">
            <x-avatar-svg :config="$config" class="w-full h-auto" />
        </div>
        <div class="flex gap-2">
            <flux:button type="button" variant="primary" wire:click="save" class="flex-1">Save avatar</flux:button>
            <flux:button type="button" variant="ghost" wire:click="randomize">Randomize</flux:button>
        </div>
        @if (auth()->user()->hasBuiltAvatar())
            <flux:button type="button" size="xs" variant="ghost" wire:click="clearBuiltAvatar"
                wire:confirm="Remove built avatar and revert to upload/Gravatar?">
                Remove built avatar
            </flux:button>
        @endif
    </div>

    {{-- Pickers --}}
    <div class="grid gap-5 sm:grid-cols-2">
        @php
            $styleLabels = [
                'tshirt' => 'T-shirt', 'dress-shirt' => 'Dress shirt', 'hoodie' => 'Hoodie', 'dress' => 'Dress',
                'pants' => 'Pants', 'shorts' => 'Shorts', 'skirt' => 'Skirt',
                'sneakers' => 'Sneakers', 'boots' => 'Boots', 'flats' => 'Flats',
                'short' => 'Short', 'long' => 'Long', 'buzz' => 'Buzz', 'bun' => 'Bun', 'bald' => 'Bald',
                'default' => 'Default', 'happy' => 'Happy', 'wink' => 'Wink',
                'smile' => 'Smile', 'neutral' => 'Neutral', 'grin' => 'Grin',
                'none' => 'None', 'mustache' => 'Mustache', 'beard' => 'Beard',
                'cap' => 'Cap', 'beanie' => 'Beanie', 'tophat' => 'Top hat',
            ];
        @endphp

        @php
            $stylePillClass = function (bool $active): string {
                return $active
                    ? 'px-3 py-1.5 rounded-full text-xs font-medium bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                    : 'px-3 py-1.5 rounded-full text-xs font-medium bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200';
            };
            $swatchClass = function (bool $active): string {
                return 'w-7 h-7 rounded-full ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 transition '
                    . ($active ? 'ring-2 ring-zinc-900 dark:ring-white scale-110' : 'ring-1 ring-zinc-300 dark:ring-zinc-600 hover:scale-105');
            };
        @endphp

        {{-- Skin --}}
        <div>
            <flux:heading size="sm">Skin</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['skinTones'] as $color)
                    <button type="button" wire:click="setSkin('{{ $color }}')"
                        class="{{ $swatchClass($config['skin'] === $color) }}"
                        style="background-color: {{ $color }}"
                        aria-label="Skin {{ $color }}"></button>
                @endforeach
            </div>
        </div>

        {{-- Hair --}}
        <div>
            <flux:heading size="sm">Hair</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['hairStyles'] as $style)
                    <button type="button" wire:click="setHairStyle('{{ $style }}')"
                        class="{{ $stylePillClass($config['hair']['style'] === $style) }}">
                        {{ $styleLabels[$style] ?? ucfirst($style) }}
                    </button>
                @endforeach
            </div>
            @if ($config['hair']['style'] !== 'bald')
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($opts['hairColors'] as $color)
                        <button type="button" wire:click="setHairColor('{{ $color }}')"
                            class="{{ $swatchClass($config['hair']['color'] === $color) }}"
                            style="background-color: {{ $color }}"
                            aria-label="Hair {{ $color }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Face --}}
        <div>
            <flux:heading size="sm">Eyes</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['eyeStyles'] as $style)
                    <button type="button" wire:click="setEyes('{{ $style }}')"
                        class="{{ $stylePillClass($config['eyes'] === $style) }}">
                        {{ $styleLabels[$style] }}
                    </button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['eyeColors'] as $color)
                    <button type="button" wire:click="setEyeColor('{{ $color }}')"
                        class="{{ $swatchClass($config['eye_color'] === $color) }}"
                        style="background-color: {{ $color }}"
                        aria-label="Eye {{ $color }}"></button>
                @endforeach
            </div>
        </div>

        <div>
            <flux:heading size="sm">Mouth</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['mouthStyles'] as $style)
                    <button type="button" wire:click="setMouth('{{ $style }}')"
                        class="{{ $stylePillClass($config['mouth'] === $style) }}">
                        {{ $styleLabels[$style] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            <flux:heading size="sm">Facial hair</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['facialHairStyles'] as $style)
                    <button type="button" wire:click="setFacialHair('{{ $style }}')"
                        class="{{ $stylePillClass($config['facial_hair'] === $style) }}">
                        {{ $styleLabels[$style] }}
                    </button>
                @endforeach
            </div>
            @if ($config['facial_hair'] !== 'none')
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($opts['facialHairColors'] as $color)
                        <button type="button" wire:click="setFacialHairColor('{{ $color }}')"
                            class="{{ $swatchClass($config['facial_hair_color'] === $color) }}"
                            style="background-color: {{ $color }}"
                            aria-label="Facial hair {{ $color }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top --}}
        <div>
            <flux:heading size="sm">Top</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['topStyles'] as $style)
                    <button type="button" wire:click="setTopStyle('{{ $style }}')"
                        class="{{ $stylePillClass($config['top']['style'] === $style) }}">
                        {{ $styleLabels[$style] }}
                    </button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['topColors'] as $color)
                    <button type="button" wire:click="setTopColor('{{ $color }}')"
                        class="{{ $swatchClass($config['top']['color'] === $color) }}"
                        style="background-color: {{ $color }}"
                        aria-label="Top {{ $color }}"></button>
                @endforeach
            </div>
        </div>

        {{-- Bottom --}}
        @if ($config['top']['style'] !== 'dress')
            <div>
                <flux:heading size="sm">Bottom</flux:heading>
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($opts['bottomStyles'] as $style)
                        <button type="button" wire:click="setBottomStyle('{{ $style }}')"
                            class="{{ $stylePillClass($config['bottom']['style'] === $style) }}">
                            {{ $styleLabels[$style] }}
                        </button>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($opts['bottomColors'] as $color)
                        <button type="button" wire:click="setBottomColor('{{ $color }}')"
                            class="{{ $swatchClass($config['bottom']['color'] === $color) }}"
                            style="background-color: {{ $color }}"
                            aria-label="Bottom {{ $color }}"></button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Shoes --}}
        <div>
            <flux:heading size="sm">Shoes</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['shoeStyles'] as $style)
                    <button type="button" wire:click="setShoeStyle('{{ $style }}')"
                        class="{{ $stylePillClass($config['shoes']['style'] === $style) }}">
                        {{ $styleLabels[$style] }}
                    </button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['shoeColors'] as $color)
                    <button type="button" wire:click="setShoeColor('{{ $color }}')"
                        class="{{ $swatchClass($config['shoes']['color'] === $color) }}"
                        style="background-color: {{ $color }}"
                        aria-label="Shoes {{ $color }}"></button>
                @endforeach
            </div>
        </div>

        {{-- Hat --}}
        <div>
            <flux:heading size="sm">Headwear</flux:heading>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($opts['hatStyles'] as $style)
                    <button type="button" wire:click="setHatStyle('{{ $style }}')"
                        class="{{ $stylePillClass($config['hat']['style'] === $style) }}">
                        {{ $styleLabels[$style] }}
                    </button>
                @endforeach
            </div>
            @if ($config['hat']['style'] !== 'none')
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($opts['hatColors'] as $color)
                        <button type="button" wire:click="setHatColor('{{ $color }}')"
                            class="{{ $swatchClass($config['hat']['color'] === $color) }}"
                            style="background-color: {{ $color }}"
                            aria-label="Hat {{ $color }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
