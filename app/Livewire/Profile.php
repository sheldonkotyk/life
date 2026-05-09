<?php

namespace App\Livewire;

use App\Models\Household;
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

    public string $joinCode = '';

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

    public function joinHousehold(): void
    {
        $this->validate([
            'joinCode' => ['required', 'string', 'max:12'],
        ]);

        $code = strtoupper(trim($this->joinCode));
        $household = Household::where('invite_code', $code)->first();

        if (! $household) {
            $this->addError('joinCode', 'No household found for that code.');

            return;
        }

        $user = auth()->user();

        if ($user->household_id === $household->id) {
            $this->addError('joinCode', 'You are already in that household.');

            return;
        }

        $user->joinHousehold($household);

        $this->joinCode = '';
        session()->flash('status', 'You joined '.$household->name.'.');
        $this->redirectRoute('household', navigate: true);
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
