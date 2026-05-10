<div class="grid gap-6 md:grid-cols-[260px_1fr]">
    {{-- Preview --}}
    <div class="space-y-3">
        <div class="rounded-2xl overflow-hidden sticky top-4">
            <x-avatar-svg :config="$config" class="w-full h-auto block" />
        </div>
        <div class="flex gap-2">
            <flux:button type="button" variant="primary" wire:click="save" class="flex-1">Save avatar</flux:button>
            <flux:button type="button" variant="ghost" icon="sparkles" wire:click="randomize" title="Randomize" aria-label="Randomize" />
        </div>
        @if (auth()->user()->hasBuiltAvatar())
            <flux:button type="button" size="xs" variant="ghost" wire:click="clearBuiltAvatar"
                wire:confirm="Remove built avatar and revert to uploaded image?">
                Remove built avatar
            </flux:button>
        @endif
    </div>

    {{-- Pickers --}}
    <div>
        @php
            $styleLabels = [
                'tshirt' => 'T-shirt', 'dress-shirt' => 'Dress shirt', 'hoodie' => 'Hoodie', 'dress' => 'Dress',
                'pants' => 'Pants', 'shorts' => 'Shorts', 'skirt' => 'Skirt',
                'sneakers' => 'Sneakers', 'boots' => 'Boots', 'flats' => 'Flats', 'sandals' => 'Sandals',
                'short' => 'Short', 'long' => 'Long', 'buzz' => 'Buzz', 'bun' => 'Bun', 'bald' => 'Bald',
                'default' => 'Default', 'happy' => 'Happy', 'wink' => 'Wink',
                'smile' => 'Smile', 'neutral' => 'Neutral', 'grin' => 'Grin',
                'none' => 'None', 'mustache' => 'Mustache', 'goatee' => 'Goatee', 'beard' => 'Beard',
                'button' => 'Button', 'pointed' => 'Pointed', 'wide' => 'Wide',
                'small' => 'Small', 'large' => 'Large',
                'cap' => 'Cap', 'beanie' => 'Beanie', 'tophat' => 'Top hat',
                'slim' => 'Slim', 'average' => 'Average', 'broad' => 'Broad', 'kid' => 'Kid',
                'tall' => 'Tall',
            ];

            $thumbClass = function (bool $active): string {
                return 'rounded-lg overflow-hidden p-0.5 bg-zinc-50 dark:bg-zinc-800 transition '
                    .($active
                        ? 'ring-2 ring-zinc-900 dark:ring-white'
                        : 'ring-1 ring-zinc-300 dark:ring-zinc-600 hover:ring-zinc-500 dark:hover:ring-zinc-400');
            };
            $swatchClass = function (bool $active): string {
                return 'h-5 w-5 rounded-full ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 transition '
                    .($active ? 'ring-2 ring-zinc-900 dark:ring-white' : 'ring-1 ring-zinc-300 dark:ring-zinc-600 hover:scale-105');
            };

            $thumbConfig = function (array $overrides) use ($config) {
                return array_replace_recursive($config, $overrides);
            };
        @endphp

        <flux:tab.group>
            <flux:tabs>
                <flux:tab name="general">General</flux:tab>
                <flux:tab name="face">Face</flux:tab>
                <flux:tab name="clothing">Clothing</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="general" class="grid gap-5 sm:grid-cols-2">
                {{-- Skin / body --}}
                <div>
                    <flux:heading size="sm">Body type</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['bodyTypes'] as $type)
                            <button type="button" wire:click="setBodyType('{{ $type }}')"
                                title="{{ $styleLabels[$type] }}" aria-label="{{ $styleLabels[$type] }}"
                                class="{{ $thumbClass($config['body_type'] === $type) }}">
                                <x-avatar-svg :config="$thumbConfig(['body_type' => $type])" class="w-14 h-[72px] block" />
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <flux:heading size="sm">Height</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['heights'] as $height)
                            <button type="button" wire:click="setHeight('{{ $height }}')"
                                title="{{ $styleLabels[$height] }}" aria-label="{{ $styleLabels[$height] }}"
                                class="{{ $thumbClass($config['height'] === $height) }}">
                                <x-avatar-svg :config="$thumbConfig(['height' => $height])" class="w-14 h-[72px] block" />
                            </button>
                        @endforeach
                    </div>
                </div>

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
            </flux:tab.panel>

            <flux:tab.panel name="face" class="grid gap-5 sm:grid-cols-2">
                {{-- Hair --}}
                <div>
                    <flux:heading size="sm">Hair</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['hairStyles'] as $style)
                            <button type="button" wire:click="setHairStyle('{{ $style }}')"
                                title="{{ $styleLabels[$style] ?? ucfirst($style) }}"
                                aria-label="{{ $styleLabels[$style] ?? ucfirst($style) }}"
                                class="{{ $thumbClass($config['hair']['style'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['hair' => ['style' => $style]])" class="w-14 h-[72px] block" />
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

                {{-- Eyes --}}
                <div>
                    <flux:heading size="sm">Eyes</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['eyeStyles'] as $style)
                            <button type="button" wire:click="setEyes('{{ $style }}')"
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['eyes'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['eyes' => $style])" class="w-14 h-[72px] block" />
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
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['mouth'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['mouth' => $style])" class="w-14 h-[72px] block" />
                            </button>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['mouthColors'] as $color)
                            <button type="button" wire:click="setMouthColor('{{ $color }}')"
                                class="{{ $swatchClass($config['mouth_color'] === $color) }}"
                                style="background-color: {{ $color }}"
                                aria-label="Mouth {{ $color }}"></button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <flux:heading size="sm">Nose</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['noseStyles'] as $style)
                            <button type="button" wire:click="setNose('{{ $style }}')"
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['nose'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['nose' => $style])" class="w-14 h-[72px] block" />
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <flux:heading size="sm">Ears</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['earStyles'] as $style)
                            <button type="button" wire:click="setEars('{{ $style }}')"
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['ears'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['ears' => $style])" class="w-14 h-[72px] block" />
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <flux:heading size="sm">Facial hair</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['facialHairStyles'] as $style)
                            <button type="button" wire:click="setFacialHair('{{ $style }}')"
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['facial_hair'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['facial_hair' => $style])" class="w-14 h-[72px] block" />
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
            </flux:tab.panel>

            <flux:tab.panel name="clothing" class="grid gap-5 sm:grid-cols-2">
                {{-- Top --}}
                <div>
                    <flux:heading size="sm">Top</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($opts['topStyles'] as $style)
                            <button type="button" wire:click="setTopStyle('{{ $style }}')"
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['top']['style'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['top' => ['style' => $style]])" class="w-14 h-[72px] block" />
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
                                    title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                    class="{{ $thumbClass($config['bottom']['style'] === $style) }}">
                                    <x-avatar-svg :config="$thumbConfig(['bottom' => ['style' => $style]])" class="w-14 h-[72px] block" />
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
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['shoes']['style'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['shoes' => ['style' => $style]])" class="w-14 h-[72px] block" />
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
                                title="{{ $styleLabels[$style] }}" aria-label="{{ $styleLabels[$style] }}"
                                class="{{ $thumbClass($config['hat']['style'] === $style) }}">
                                <x-avatar-svg :config="$thumbConfig(['hat' => ['style' => $style]])" class="w-14 h-[72px] block" />
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
            </flux:tab.panel>
        </flux:tab.group>
    </div>
</div>
