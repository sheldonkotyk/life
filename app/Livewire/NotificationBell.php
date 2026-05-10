<?php

namespace App\Livewire;

use Illuminate\Support\Collection;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->markVisibleAsRead();
        }
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function markRead(string $id): void
    {
        auth()->user()?->notifications()->whereKey($id)->update(['read_at' => now()]);
    }

    protected function markVisibleAsRead(): void
    {
        $ids = $this->recent()->where('read_at', null)->pluck('id');

        if ($ids->isNotEmpty()) {
            auth()->user()->notifications()->whereIn('id', $ids)->update(['read_at' => now()]);
        }
    }

    public function getUnreadCountProperty(): int
    {
        return (int) (auth()->user()?->unreadNotifications()->count() ?? 0);
    }

    public function getRecentProperty(): Collection
    {
        return auth()->user()?->notifications()->latest()->limit(15)->get() ?? collect();
    }

    protected function recent(): Collection
    {
        return $this->getRecentProperty();
    }

    public function render()
    {
        return view('livewire.notification-bell', [
            'unreadCount' => $this->unreadCount,
            'notifications' => $this->recent,
        ]);
    }
}
