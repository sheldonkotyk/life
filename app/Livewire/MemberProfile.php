<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FoodPreference;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MemberProfile extends Component
{
    public const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    public FamilyMember $member;

    #[Url(as: 'tab')]
    public string $tab = 'profile';

    public string $name = '';

    public string $color = '#6366f1';

    public bool $isChild = false;

    public bool $isGuest = false;

    public string $notes = '';

    public ?string $birthday = null;

    public string $timezone = 'UTC';

    public string $newAllergy = '';

    public string $prefFood = '';

    public string $prefType = 'like';

    public string $prefNotes = '';

    public bool $addingPreference = false;

    public function mount(FamilyMember $member): void
    {
        abort_unless($member->household_id === auth()->user()->household_id, 404);

        $this->member = $member;
        $this->name = $member->name;
        $this->color = $member->color;
        $this->isChild = (bool) $member->is_child;
        $this->isGuest = (bool) $member->is_guest;
        $this->notes = $member->notes ?? '';
        $this->birthday = $member->birthday?->format('Y-m-d');
        $this->timezone = $member->user?->getTimezone() ?? 'UTC';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'max:7'],
            'isChild' => ['boolean'],
            'isGuest' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'birthday' => ['nullable', 'date'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    public function save(): void
    {
        $this->ensureCanEdit();
        $this->validate();

        $this->member->update([
            'name' => $this->name,
            'color' => $this->color,
            'is_child' => $this->isChild,
            'is_guest' => $this->isGuest,
            'notes' => $this->notes ?: null,
            'birthday' => $this->birthday ?: null,
        ]);

        if ($this->canEditChildUser) {
            $this->member->user->update([
                'name' => $this->name,
                'birthday' => $this->birthday ?: null,
                'timezone' => $this->timezone,
            ]);
        }

        session()->flash('status', 'Saved.');
    }

    public function addAllergy(): void
    {
        $this->ensureCanEdit();
        $this->validate(['newAllergy' => ['required', 'string', 'max:80']]);

        FoodPreference::create([
            'family_member_id' => $this->member->id,
            'food' => trim($this->newAllergy),
            'type' => 'allergy',
        ]);

        $this->newAllergy = '';
    }

    public function addPreference(): void
    {
        $this->ensureCanEdit();
        $this->validate([
            'prefFood' => ['required', 'string', 'max:80'],
            'prefType' => ['required', 'in:like,dislike,allergy'],
        ]);

        FoodPreference::create([
            'family_member_id' => $this->member->id,
            'food' => $this->prefFood,
            'type' => $this->prefType,
            'notes' => $this->prefNotes ?: null,
        ]);

        $this->reset(['prefFood', 'prefNotes']);
        $this->prefType = 'like';
        $this->addingPreference = false;
    }

    public function removePreference(int $prefId): void
    {
        $this->ensureCanEdit();
        FoodPreference::where('family_member_id', $this->member->id)
            ->where('id', $prefId)
            ->delete();
    }

    public function startAddingPreference(): void
    {
        $this->addingPreference = true;
        $this->prefFood = '';
        $this->prefType = 'like';
        $this->prefNotes = '';
    }

    public function toggleAttendance(string $day, string $slot): void
    {
        $this->ensureCanEditAttendance();

        if (! in_array($day, self::DAYS, true) || ! in_array($slot, self::SLOTS, true)) {
            return;
        }

        $this->member->setDefaultAttendance($day, $slot, ! $this->member->attendsByDefault($day, $slot));
    }

    public function setDayAttendance(string $day, bool $value): void
    {
        $this->ensureCanEditAttendance();

        if (! in_array($day, self::DAYS, true)) {
            return;
        }

        foreach (self::SLOTS as $slot) {
            $this->member->setDefaultAttendance($day, $slot, $value);
        }
    }

    public function setSlotAttendance(string $slot, bool $value): void
    {
        $this->ensureCanEditAttendance();

        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }

        foreach (self::DAYS as $day) {
            $this->member->setDefaultAttendance($day, $slot, $value);
        }
    }

    private function ensureCanEditAttendance(): void
    {
        $this->ensureCanEdit();
    }

    private function ensureCanEdit(): void
    {
        $user = auth()->user();
        $canManage = $user->canManageHousehold($user->household);
        abort_unless($canManage || $this->member->user_id === $user->id, 403);
    }

    public function getCanEditProperty(): bool
    {
        $user = auth()->user();

        return $user->canManageHousehold($user->household) || $this->member->user_id === $user->id;
    }

    public function getCanEditChildUserProperty(): bool
    {
        $user = auth()->user();

        return $user->canManageHousehold($user->household)
            && $this->member->user_id !== null
            && $this->member->user_id !== $user->id
            && (bool) $this->member->is_child;
    }

    public function getCanManageAvatarProperty(): bool
    {
        $user = auth()->user();
        $isAdmin = $user->canManageHousehold($user->household);

        return $isAdmin || $this->member->user_id === $user->id;
    }

    public function delete(): void
    {
        abort_if($this->member->user_id, 403);
        $this->ensureCanEdit();
        $this->member->delete();
        $this->redirectRoute('household', navigate: true);
    }

    #[On('avatar-updated')]
    public function refreshMember(): void
    {
        $this->member->refresh();
    }

    public function render()
    {
        return view('livewire.member-profile', [
            'preferences' => $this->member->preferences()->orderBy('food')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }
}
