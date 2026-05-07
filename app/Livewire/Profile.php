<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $timezone = 'UTC';

    public ?string $birthday = null;

    public $avatar = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->timezone = $user->getTimezone();
        $this->birthday = $user->birthday?->format('Y-m-d');
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'birthday' => ['nullable', 'date'],
        ]);

        auth()->user()->update([
            'name' => $this->name,
            'timezone' => $this->timezone,
            'birthday' => $this->birthday ?: null,
        ]);
        session()->flash('status', 'Profile updated.');
    }

    public function updatedAvatar(): void
    {
        $this->validate(['avatar' => ['image', 'max:1024']]);

        $user = auth()->user();
        $existing = $user->getRawOriginal('avatar');
        if ($existing && ! str_starts_with($existing, 'http')) {
            Storage::disk('public')->delete($existing);
        }

        $user->update(['avatar' => $this->avatar->store('avatars', 'public')]);
        $this->avatar = null;
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();
        $existing = $user->getRawOriginal('avatar');
        if ($existing && ! str_starts_with($existing, 'http')) {
            Storage::disk('public')->delete($existing);
        }
        $user->update(['avatar' => null]);
        $this->avatar = null;
        session()->flash('status', 'Avatar removed.');
    }

    public function render()
    {
        return view('livewire.profile', [
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }
}
