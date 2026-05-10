@props([
    'member',
    'size' => 'md', // xs, sm, md, lg
])

@php
    $sizes = [
        'xs' => 'w-4 h-4 text-[8px]',
        'sm' => 'w-5 h-5 text-[10px]',
        'md' => 'w-7 h-7 text-xs',
        'lg' => 'w-10 h-10 text-base',
    ];
    $classes = $sizes[$size] ?? $sizes['md'];
    $initial = strtoupper(mb_substr($member->name, 0, 1));
    $user = $member->user;
    $hasBuilt = $user?->hasBuiltAvatar();
    $avatarUrl = $hasBuilt ? null : $user?->avatar;
@endphp

@if ($hasBuilt)
    <span
        {{ $attributes->merge(['class' => "$classes rounded-full overflow-hidden ring-1 ring-white shadow-sm shrink-0 inline-block"]) }}
        title="{{ $member->name }}"
    >
        <x-avatar-svg :config="$user->avatar_config" class="w-full h-full" />
    </span>
@elseif ($avatarUrl)
    <img
        src="{{ $avatarUrl }}"
        alt="{{ $member->name }}"
        title="{{ $member->name }}"
        {{ $attributes->merge(['class' => "$classes rounded-full object-cover ring-1 ring-white shadow-sm shrink-0"]) }}
    />
@else
    <span
        {{ $attributes->merge(['class' => "$classes rounded-full inline-flex items-center justify-center text-white font-semibold ring-1 ring-white shadow-sm shrink-0"]) }}
        style="background-color: {{ $member->color }}"
        title="{{ $member->name }}"
    >{{ $initial }}</span>
@endif
