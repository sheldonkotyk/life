<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FoodPreference;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Family extends Component
{
    public const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    public ?int $editingId = null;

    public string $name = '';

    public string $color = '#6366f1';

    public bool $isChild = false;

    public bool $isGuest = false;

    public string $notes = '';

    public ?float $targetCalories = null;

    public ?float $targetProteinG = null;

    public ?float $targetCarbsG = null;

    public ?float $targetFatG = null;

    public ?int $prefMemberId = null;

    public string $prefFood = '';

    public string $prefType = 'like';

    public string $prefNotes = '';

    public string $newAllergy = '';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'max:7'],
            'isChild' => ['boolean'],
            'isGuest' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'targetCalories' => ['nullable', 'numeric', 'min:0'],
            'targetProteinG' => ['nullable', 'numeric', 'min:0'],
            'targetCarbsG' => ['nullable', 'numeric', 'min:0'],
            'targetFatG' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->validate();
        $data = [
            'household_id' => auth()->user()->household_id,
            'name' => $this->name,
            'color' => $this->color,
            'is_child' => $this->isChild,
            'is_guest' => $this->isGuest,
            'notes' => $this->notes ?: null,
            'target_calories' => $this->targetCalories ?: null,
            'target_protein_g' => $this->targetProteinG ?: null,
            'target_carbs_g' => $this->targetCarbsG ?: null,
            'target_fat_g' => $this->targetFatG ?: null,
        ];

        if ($this->editingId) {
            FamilyMember::where('id', $this->editingId)
                ->where('household_id', auth()->user()->household_id)
                ->update($data);
        } else {
            FamilyMember::create($data);
        }

        $this->resetForm();
        $this->modal('member-form')->close();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->modal('member-form')->show();
    }

    public function edit(int $id): void
    {
        $m = $this->householdMembers()->findOrFail($id);
        $this->editingId = $m->id;
        $this->name = $m->name;
        $this->color = $m->color;
        $this->isChild = $m->is_child;
        $this->isGuest = $m->is_guest;
        $this->notes = $m->notes ?? '';
        $this->targetCalories = $m->target_calories;
        $this->targetProteinG = $m->target_protein_g;
        $this->targetCarbsG = $m->target_carbs_g;
        $this->targetFatG = $m->target_fat_g;
        $this->modal('member-form')->show();
    }

    public function delete(int $id): void
    {
        $this->householdMembers()->where('id', $id)->delete();
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'color', 'isChild', 'isGuest', 'notes', 'targetCalories', 'targetProteinG', 'targetCarbsG', 'targetFatG', 'newAllergy']);
        $this->color = '#6366f1';
    }

    public function getEditingAllergiesProperty()
    {
        if (! $this->editingId) {
            return collect();
        }

        return FoodPreference::whereHas('familyMember', fn ($q) => $q->where('household_id', auth()->user()->household_id))
            ->where('family_member_id', $this->editingId)
            ->where('type', 'allergy')
            ->orderBy('food')
            ->get();
    }

    public function addPreference(int $memberId): void
    {
        $this->validate([
            'prefFood' => ['required', 'string', 'max:80'],
            'prefType' => ['required', 'in:like,dislike,allergy'],
        ]);

        $member = $this->householdMembers()->findOrFail($memberId);
        FoodPreference::create([
            'family_member_id' => $member->id,
            'food' => $this->prefFood,
            'type' => $this->prefType,
            'notes' => $this->prefNotes ?: null,
        ]);

        $this->reset(['prefFood', 'prefNotes']);
        $this->prefMemberId = null;
    }

    public function addAllergy(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->validate([
            'newAllergy' => ['required', 'string', 'max:80'],
        ]);

        $member = $this->householdMembers()->findOrFail($this->editingId);
        FoodPreference::create([
            'family_member_id' => $member->id,
            'food' => trim($this->newAllergy),
            'type' => 'allergy',
        ]);

        $this->newAllergy = '';
    }

    public function removePreference(int $prefId): void
    {
        FoodPreference::whereHas('familyMember', fn ($q) => $q->where('household_id', auth()->user()->household_id))
            ->where('id', $prefId)
            ->delete();
    }

    public function startAddingPreference(int $memberId): void
    {
        $this->prefMemberId = $memberId;
        $this->prefFood = '';
        $this->prefType = 'like';
        $this->prefNotes = '';
    }

    public function canEditAttendance(?FamilyMember $member): bool
    {
        if (! $member) {
            return false;
        }

        $user = auth()->user();

        if ($user->canManageHousehold($user->household)) {
            return true;
        }

        return $member->user_id === $user->id;
    }

    public function getEditingMemberProperty(): ?FamilyMember
    {
        if (! $this->editingId) {
            return null;
        }

        return $this->householdMembers()->find($this->editingId);
    }

    public function toggleAttendance(string $day, string $slot): void
    {
        $member = $this->editingMember;
        abort_unless($member && $this->canEditAttendance($member), 403);

        if (! in_array($day, self::DAYS, true) || ! in_array($slot, self::SLOTS, true)) {
            return;
        }

        $member->setDefaultAttendance($day, $slot, ! $member->attendsByDefault($day, $slot));
    }

    public function setDayAttendance(string $day, bool $value): void
    {
        $member = $this->editingMember;
        abort_unless($member && $this->canEditAttendance($member), 403);

        if (! in_array($day, self::DAYS, true)) {
            return;
        }

        foreach (self::SLOTS as $slot) {
            $member->setDefaultAttendance($day, $slot, $value);
        }
    }

    public function setSlotAttendance(string $slot, bool $value): void
    {
        $member = $this->editingMember;
        abort_unless($member && $this->canEditAttendance($member), 403);

        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }

        foreach (self::DAYS as $day) {
            $member->setDefaultAttendance($day, $slot, $value);
        }
    }

    public function makeAdmin(int $userId): void
    {
        $household = auth()->user()->household;
        abort_unless($household && auth()->user()->canManageHousehold($household), 403);
        abort_unless($household->users()->where('users.id', $userId)->exists(), 404);

        $household->users()->updateExistingPivot($userId, ['role' => 'admin']);
        session()->flash('status', 'Administrator added.');
    }

    public function removeAdmin(int $userId): void
    {
        $household = auth()->user()->household;
        abort_unless($household && auth()->user()->canManageHousehold($household), 403);
        abort_unless($household->users()->where('users.id', $userId)->exists(), 404);

        if ($household->admins()->count() <= 1 && $household->admins()->where('users.id', $userId)->exists()) {
            session()->flash('status', 'A household must have at least one administrator.');

            return;
        }

        $household->users()->updateExistingPivot($userId, ['role' => null]);
        session()->flash('status', 'Administrator removed.');
    }

    private function householdMembers()
    {
        return FamilyMember::where('household_id', auth()->user()->household_id);
    }

    public function render()
    {
        $household = auth()->user()->household;
        $adminIds = $household ? $household->admins()->pluck('users.id')->all() : [];

        return view('livewire.family', [
            'members' => $this->householdMembers()->where('is_guest', false)->with('preferences', 'user')->orderBy('is_child')->orderBy('name')->get(),
            'guests' => $this->householdMembers()->where('is_guest', true)->with('preferences', 'user')->orderBy('name')->get(),
            'adminIds' => $adminIds,
            'canManage' => $household ? auth()->user()->canManageHousehold($household) : false,
        ]);
    }
}
