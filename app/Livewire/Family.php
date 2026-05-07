<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FoodPreference;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Family extends Component
{
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

    private function householdMembers()
    {
        return FamilyMember::where('household_id', auth()->user()->household_id);
    }

    public function render()
    {
        return view('livewire.family', [
            'members' => $this->householdMembers()->visible()->with('preferences', 'user')->orderBy('is_child')->orderBy('name')->get(),
        ]);
    }
}
