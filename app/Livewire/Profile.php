<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\Household;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Profile extends Component
{
    use WithFileUploads;

    public const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    #[Url(as: 'tab')]
    public string $tab = 'profile';

    public string $name = '';

    public string $timezone = 'UTC';

    public ?string $birthday = null;

    public $avatar = null;

    public string $joinCode = '';

    public string $newHouseholdName = '';

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

        if ($user->households()->where('households.id', $household->id)->exists()) {
            $this->addError('joinCode', 'You are already in that household.');

            return;
        }

        $user->joinHousehold($household);

        $this->joinCode = '';
        session()->flash('status', 'You joined '.$household->name.'.');
        $this->redirectRoute('household', navigate: true);
    }

    public function createHousehold(): void
    {
        $this->validate([
            'newHouseholdName' => ['required', 'string', 'max:120'],
        ]);

        $user = auth()->user();
        $household = Household::create(['name' => trim($this->newHouseholdName)]);
        $user->joinHousehold($household);

        $this->newHouseholdName = '';
        session()->flash('status', 'Created '.$household->name.'.');
        $this->redirectRoute('household', navigate: true);
    }

    public function switchHousehold(int $householdId): void
    {
        $user = auth()->user();
        $household = $user->households()->where('households.id', $householdId)->first();

        abort_unless($household, 403);

        $user->forceFill(['household_id' => $household->id])->save();
        session()->flash('status', 'Switched to '.$household->name.'.');
        $this->redirectRoute('household', navigate: true);
    }

    public function leaveHousehold(int $householdId): void
    {
        $user = auth()->user();
        $household = $user->households()->where('households.id', $householdId)->first();

        abort_unless($household, 403);

        if ($user->isAdminOf($household)
            && $household->admins()->where('users.id', '!=', $user->id)->doesntExist()
            && $household->users()->where('users.id', '!=', $user->id)->exists()
        ) {
            session()->flash('error', 'Promote another admin before leaving '.$household->name.'.');

            return;
        }

        $household->users()->detach($user->id);

        if ($user->household_id === $household->id) {
            $next = $user->households()->orderBy('households.id')->first();

            if (! $next) {
                $next = Household::create(['name' => ($user->name ?: 'Your')."'s Household"]);
                $user->joinHousehold($next);
            } else {
                $user->forceFill(['household_id' => $next->id])->save();
            }
        }

        session()->flash('status', 'Left '.$household->name.'.');
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

    public function getMemberProperty(): ?FamilyMember
    {
        return auth()->user()->familyMember;
    }

    public function toggleAttendance(string $day, string $slot): void
    {
        $member = $this->member;
        abort_unless($member, 404);

        if (! in_array($day, self::DAYS, true) || ! in_array($slot, self::SLOTS, true)) {
            return;
        }

        $member->setDefaultAttendance($day, $slot, ! $member->attendsByDefault($day, $slot));
    }

    public function setDayAttendance(string $day, bool $value): void
    {
        $member = $this->member;
        abort_unless($member, 404);

        if (! in_array($day, self::DAYS, true)) {
            return;
        }

        foreach (self::SLOTS as $slot) {
            $member->setDefaultAttendance($day, $slot, $value);
        }
    }

    public function setSlotAttendance(string $slot, bool $value): void
    {
        $member = $this->member;
        abort_unless($member, 404);

        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }

        foreach (self::DAYS as $day) {
            $member->setDefaultAttendance($day, $slot, $value);
        }
    }

    public function render()
    {
        return view('livewire.profile', [
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }
}
