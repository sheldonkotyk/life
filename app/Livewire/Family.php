<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FoodPreference;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Family extends Component
{
    public string $name = '';

    public string $color = '#6366f1';

    public bool $isChild = false;

    public bool $isGuest = false;

    public string $notes = '';

    public ?int $prefMemberId = null;

    public string $prefFood = '';

    public string $prefType = 'like';

    public string $prefNotes = '';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'max:7'],
            'isChild' => ['boolean'],
            'isGuest' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        FamilyMember::create([
            'household_id' => auth()->user()->household_id,
            'name' => $this->name,
            'color' => $this->color,
            'is_child' => $this->isChild,
            'is_guest' => $this->isGuest,
            'notes' => $this->notes ?: null,
        ]);

        $this->resetForm();
        $this->modal('member-form')->close();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->modal('member-form')->show();
    }

    public function delete(int $id): void
    {
        $this->householdMembers()->where('id', $id)->whereNull('user_id')->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'color', 'isChild', 'isGuest', 'notes']);
        $this->color = '#6366f1';
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
