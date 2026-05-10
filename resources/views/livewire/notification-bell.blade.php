<div wire:poll.30s>
    <flux:dropdown position="bottom" align="end">
        <flux:button size="sm" variant="ghost" class="relative" aria-label="Notifications">
            <flux:icon icon="bell" />
            @if ($unreadCount > 0)
                <span class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold leading-4 text-center">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-2 py-1">
                <flux:heading size="sm">Notifications</flux:heading>
                @if ($unreadCount > 0)
                    <flux:button size="xs" variant="ghost" wire:click="markAllRead">Mark all read</flux:button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $title = $data['title'] ?? 'Notification';
                    $body = $data['body'] ?? null;
                    $url = $data['url'] ?? null;
                    $icon = $data['icon'] ?? 'bell';
                    $isUnread = is_null($notification->read_at);
                @endphp

                <flux:menu.item
                    :icon="$icon"
                    :href="$url"
                    wire:click="markRead('{{ $notification->id }}')"
                    class="{{ $isUnread ? 'bg-zinc-50 dark:bg-zinc-800' : '' }}"
                >
                    <div class="flex flex-col gap-0.5">
                        <span class="font-medium {{ $isUnread ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $title }}</span>
                        @if ($body)
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $body }}</span>
                        @endif
                        <span class="text-[10px] text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-3 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    You're all caught up.
                </div>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
