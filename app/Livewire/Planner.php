<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Planner extends Component
{
    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    public string $weekStart;

    #[Url(as: 'mode', except: 'plan')]
    public string $mode = 'plan';

    public ?int $memberId = null;

    public ?int $editingPlanId = null;

    public ?string $editingDate = null;

    public ?string $editingSlot = null;

    public ?int $selectedRecipeId = null;

    public array $selectedLeftoverIds = [];

    public string $customName = '';

    public string $notes = '';

    public bool $saveLeftovers = false;

    public ?int $leftoverServings = null;

    public array $attendees = [];

    public array $skippedIngredientIds = [];

    public string $newRecipeName = '';

    public ?string $startTime = null;

    public ?string $endTime = null;

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::now(auth()->user()->getTimezone())->toDateString();

        $this->memberId = auth()->user()->familyMember?->id
            ?? FamilyMember::where('household_id', auth()->user()->household_id)->value('id');

        if (! in_array($this->mode, ['plan', 'attendance'], true)) {
            $this->mode = 'plan';
        }

        $openDate = request()->query('date');
        $openSlot = request()->query('slot');
        if ($openDate && in_array($openSlot, ['breakfast', 'lunch', 'dinner', 'snack'], true)) {
            $hh = auth()->user()->household_id;
            $existing = MealPlan::where('household_id', $hh)
                ->whereDate('date', $openDate)
                ->where('slot', $openSlot)
                ->first();
            $this->openSlot($openDate, $openSlot, $existing?->id);
        }
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['plan', 'attendance'], true) ? $mode : 'plan';
    }

    public function selectMember(int $memberId): void
    {
        if ($this->selectableMembers->contains('id', $memberId)) {
            $this->memberId = $memberId;
        }
    }

    public function shiftWeek(int $weeks): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addDays($weeks * 7)->toDateString();
        $this->cancelEdit();
    }

    public function jumpToToday(): void
    {
        $this->weekStart = CarbonImmutable::now(auth()->user()->getTimezone())->toDateString();
        $this->cancelEdit();
    }

    // --- Attendance ---

    public function setAttending(string $date, string $slot, bool $attend): void
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }
        $this->guardMember();
        $this->applyAttendance([[$date, $slot]], $attend);
    }

    public function setSlotAttending(string $slot, bool $attend): void
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }
        $this->guardMember();

        $pairs = collect($this->days)->map(fn ($d) => [$d->toDateString(), $slot])->all();
        $this->applyAttendance($pairs, $attend);
    }

    public function setDayAttending(string $date, bool $attend): void
    {
        $this->guardMember();

        $pairs = array_map(fn ($slot) => [$date, $slot], self::SLOTS);
        $this->applyAttendance($pairs, $attend);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $pairs
     */
    private function applyAttendance(array $pairs, bool $attend): void
    {
        $isGuest = (bool) $this->selectedMember?->is_guest;
        $shouldHaveRow = $isGuest ? $attend : ! $attend;

        foreach ($pairs as [$date, $slot]) {
            if ($shouldHaveRow) {
                FamilyMemberUnavailability::firstOrCreate([
                    'family_member_id' => $this->memberId,
                    'date' => $date,
                    'slot' => $slot,
                ]);
            } else {
                FamilyMemberUnavailability::where('family_member_id', $this->memberId)
                    ->whereDate('date', $date)->where('slot', $slot)->delete();
            }

            if (! $attend) {
                $this->detachExistingMealAttendance($date, $slot);
            }
        }

        unset($this->notAttendingKeys);
    }

    private function guardMember(): void
    {
        abort_unless($this->members->contains('id', $this->memberId), 403);
    }

    private function detachExistingMealAttendance(string $date, string $slot): void
    {
        $hh = auth()->user()->household_id;
        MealPlan::where('household_id', $hh)
            ->whereDate('date', $date)
            ->where('slot', $slot)
            ->each(fn ($plan) => $plan->attendees()->detach($this->memberId));
    }

    // --- Modal / meal editing ---

    public function openSlot(string $date, string $slot, ?int $planId = null): void
    {
        $this->editingDate = $date;
        $this->editingSlot = $slot;
        $this->editingPlanId = $planId;

        $this->modal('edit-meal')->show();

        $hh = auth()->user()->household_id;
        $allMemberIds = FamilyMember::where('household_id', $hh)->where('is_guest', false)->pluck('id')->all();

        if ($planId) {
            $plan = MealPlan::with('attendees', 'skippedIngredients', 'leftoverSources')->where('household_id', $hh)->findOrFail($planId);
            $this->selectedRecipeId = $plan->recipe_id;
            $this->selectedLeftoverIds = $plan->leftoverSources->pluck('id')->all();
            $this->customName = $plan->custom_name ?? '';
            $this->notes = $plan->notes ?? '';
            $this->saveLeftovers = $plan->save_leftovers;
            $this->leftoverServings = $plan->leftover_servings;
            $this->attendees = $plan->attendees
                ->where('pivot.status', '!=', 'not_eating')
                ->pluck('id')->all();
            $this->skippedIngredientIds = $plan->skippedIngredients->pluck('id')->all();
            $this->startTime = $plan->start_time ? substr($plan->start_time, 0, 5) : null;
            $this->endTime = $plan->end_time ? substr($plan->end_time, 0, 5) : null;
        } else {
            $this->selectedRecipeId = null;
            $this->selectedLeftoverIds = [];
            $this->customName = '';
            $this->notes = '';
            $this->saveLeftovers = false;
            $this->leftoverServings = null;
            $unavailableIds = FamilyMemberUnavailability::whereIn('family_member_id', $allMemberIds)
                ->whereDate('date', $date)->where('slot', $slot)
                ->pluck('family_member_id')->all();
            $this->attendees = array_values(array_diff($allMemberIds, $unavailableIds));
            $this->skippedIngredientIds = [];
            $this->startTime = null;
            $this->endTime = null;
        }
    }

    public function toggleLeftover(int $id): void
    {
        if (in_array($id, $this->selectedLeftoverIds, true)) {
            $this->selectedLeftoverIds = array_values(array_diff($this->selectedLeftoverIds, [$id]));
        } else {
            $this->selectedLeftoverIds = [...$this->selectedLeftoverIds, $id];
            $this->selectedRecipeId = null;
        }
    }

    public function selectAllLeftovers(array $ids): void
    {
        $this->selectedLeftoverIds = $ids;
        if (! empty($ids)) {
            $this->selectedRecipeId = null;
        }
    }

    public function clearLeftovers(): void
    {
        $this->selectedLeftoverIds = [];
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingPlanId', 'editingDate', 'editingSlot', 'selectedRecipeId', 'selectedLeftoverIds', 'customName', 'notes', 'saveLeftovers', 'leftoverServings', 'attendees', 'skippedIngredientIds', 'newRecipeName', 'startTime', 'endTime']);
        $this->modal('edit-meal')->close();
    }

    public function createRecipeFromName(): void
    {
        $name = trim($this->newRecipeName);
        if ($name === '') {
            return;
        }

        $recipe = Recipe::create([
            'household_id' => auth()->user()->household_id,
            'name' => $name,
            'servings' => 4,
        ]);

        $this->selectedRecipeId = $recipe->id;
        $this->selectedLeftoverIds = [];
        $this->newRecipeName = '';
    }

    public function savePlan(): void
    {
        $hh = auth()->user()->household_id;

        $this->validate([
            'startTime' => ['nullable', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i', 'after:startTime'],
        ]);

        $usingLeftovers = ! empty($this->selectedLeftoverIds);

        $data = [
            'household_id' => $hh,
            'date' => $this->editingDate,
            'slot' => $this->editingSlot,
            'recipe_id' => $usingLeftovers ? null : $this->selectedRecipeId,
            'custom_name' => $this->customName ?: null,
            'notes' => $this->notes ?: null,
            'save_leftovers' => $this->saveLeftovers,
            'leftover_servings' => $this->saveLeftovers ? $this->leftoverServings : null,
            'start_time' => $this->startTime ?: null,
            'end_time' => $this->endTime ?: null,
        ];

        if ($this->editingPlanId) {
            $plan = MealPlan::where('household_id', $hh)->findOrFail($this->editingPlanId);
            $plan->update($data);
        } else {
            $plan = MealPlan::create($data);
        }

        $sourceIds = MealPlan::where('household_id', $hh)
            ->whereIn('id', $this->selectedLeftoverIds)
            ->where('id', '!=', $plan->id)
            ->pluck('id');
        $plan->leftoverSources()->sync($sourceIds);

        $skippingIds = $plan->attendees()
            ->wherePivot('status', 'not_eating')
            ->pluck('family_members.id')->all();
        $attendingIds = FamilyMember::where('household_id', $hh)
            ->whereIn('id', $this->attendees)
            ->whereNotIn('id', $skippingIds)
            ->pluck('id')->all();
        $syncData = array_fill_keys($attendingIds, ['status' => 'eating'])
            + array_fill_keys($skippingIds, ['status' => 'not_eating']);
        $plan->attendees()->sync($syncData);

        $effectiveRecipeId = $plan->recipe_id ?? $plan->leftoverSources()->first()?->recipe_id;
        if ($effectiveRecipeId) {
            $validIngredientIds = RecipeIngredient::where('recipe_id', $effectiveRecipeId)
                ->whereIn('id', $this->skippedIngredientIds)->pluck('id');
            $plan->skippedIngredients()->sync($validIngredientIds);
        } else {
            $plan->skippedIngredients()->sync([]);
        }

        $this->cancelEdit();
    }

    public function clearPlan(): void
    {
        if ($this->editingPlanId) {
            MealPlan::where('household_id', auth()->user()->household_id)
                ->where('id', $this->editingPlanId)
                ->delete();
        }
        $this->cancelEdit();
    }

    public function quickToggleAttendee(int $planId, int $memberId): void
    {
        $hh = auth()->user()->household_id;
        $plan = MealPlan::where('household_id', $hh)->findOrFail($planId);
        if ($plan->attendees()->where('family_members.id', $memberId)->exists()) {
            $plan->attendees()->detach($memberId);
        } else {
            $plan->attendees()->attach($memberId);
        }
    }

    // --- Computed properties (used by both islands) ---

    #[Computed]
    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->weekStart);
    }

    #[Computed]
    public function days()
    {
        $start = $this->start;

        return collect(range(0, 6))->map(fn ($i) => $start->addDays($i));
    }

    #[Computed]
    public function today(): string
    {
        return CarbonImmutable::today(auth()->user()->getTimezone())->toDateString();
    }

    #[Computed]
    public function members()
    {
        return FamilyMember::where('household_id', auth()->user()->household_id)
            ->with('user')
            ->orderBy('is_guest')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedMember(): ?FamilyMember
    {
        return $this->members->firstWhere('id', $this->memberId);
    }

    #[Computed]
    public function selectableMembers()
    {
        $user = auth()->user();

        if ($user->isAdminOf($user->household)) {
            return $this->members;
        }

        $ownId = $user->familyMember?->id;

        return $this->members->filter(fn ($m) => $m->is_guest || $m->id === $ownId)->values();
    }

    #[Computed]
    public function notAttendingKeys(): array
    {
        if (! $this->memberId) {
            return [];
        }

        $start = $this->start;
        $isGuest = (bool) $this->selectedMember?->is_guest;

        $overrideKeys = FamilyMemberUnavailability::where('family_member_id', $this->memberId)
            ->whereBetween('date', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->get()
            ->map(fn ($u) => $u->date->toDateString().'|'.$u->slot)
            ->all();

        if (! $isGuest) {
            return $overrideKeys;
        }

        $allKeys = [];
        foreach ($this->days as $d) {
            foreach (self::SLOTS as $slot) {
                $allKeys[] = $d->toDateString().'|'.$slot;
            }
        }

        return array_values(array_diff($allKeys, $overrideKeys));
    }

    #[Computed]
    public function plans()
    {
        $start = $this->start;
        $end = $start->addDays(6);

        return MealPlan::where('household_id', auth()->user()->household_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('recipe.ingredients', 'attendees.user', 'leftoverSources.recipe.ingredients', 'skippedIngredients')
            ->get()
            ->groupBy(fn ($p) => $p->date->toDateString().'|'.$p->slot);
    }

    #[Computed]
    public function defaultAttendees(): array
    {
        $start = $this->start;
        $end = $start->addDays(6);

        $unavailabilities = FamilyMemberUnavailability::whereIn('family_member_id', $this->members->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($u) => $u->date->toDateString().'|'.$u->slot)
            ->map(fn ($group) => $group->pluck('family_member_id')->all());

        $defaults = [];
        foreach ($this->days as $d) {
            foreach (self::SLOTS as $slot) {
                $key = $d->toDateString().'|'.$slot;
                $rowIds = $unavailabilities->get($key, []);
                $defaults[$key] = $this->members
                    ->filter(fn ($m) => $m->is_guest ? in_array($m->id, $rowIds) : ! in_array($m->id, $rowIds))
                    ->values();
            }
        }

        return $defaults;
    }

    #[Computed]
    public function recipes()
    {
        return Recipe::where('household_id', auth()->user()->household_id)->orderBy('name')->get();
    }

    #[Computed]
    public function availableLeftovers()
    {
        $start = $this->start;
        $end = $start->addDays(6);

        $consumedLeftoverIds = DB::table('meal_plan_leftover_uses')
            ->pluck('source_meal_plan_id')->all();

        return MealPlan::where('household_id', auth()->user()->household_id)
            ->where('save_leftovers', true)
            ->whereNotIn('id', $consumedLeftoverIds)
            ->whereDate('date', '>=', $start->subDays(3)->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->when($this->editingDate && $this->editingSlot, function ($q) {
                $q->where(function ($q) {
                    $q->whereDate('date', '!=', $this->editingDate)
                        ->orWhere('slot', '!=', $this->editingSlot);
                });
            })
            ->with('recipe')
            ->get();
    }

    #[Computed]
    public function activeIngredients()
    {
        if (! ($this->editingDate && $this->selectedRecipeId && empty($this->selectedLeftoverIds))) {
            return collect();
        }

        $recipe = Recipe::where('household_id', auth()->user()->household_id)
            ->with('ingredients')
            ->find($this->selectedRecipeId);

        return $recipe?->ingredients ?? collect();
    }

    #[Computed]
    public function activeRecipeServings(): int
    {
        if (! ($this->editingDate && $this->selectedRecipeId && empty($this->selectedLeftoverIds))) {
            return 1;
        }

        $recipe = Recipe::where('household_id', auth()->user()->household_id)
            ->find($this->selectedRecipeId);

        return max(1, (int) ($recipe?->servings ?? 1));
    }

    public function render()
    {
        return view('livewire.planner');
    }
}
