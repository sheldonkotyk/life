<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Planner extends Component
{
    public string $weekStart;

    public ?int $editingPlanId = null;

    public ?string $editingDate = null;

    public ?string $editingSlot = null;

    public ?int $selectedRecipeId = null;

    public ?int $selectedLeftoverId = null;

    public string $customName = '';

    public string $notes = '';

    public bool $saveLeftovers = false;

    public ?int $leftoverServings = null;

    public array $attendees = [];

    public array $skippedIngredientIds = [];

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::now(auth()->user()->getTimezone())->toDateString();
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

    public function openSlot(string $date, string $slot, ?int $planId = null): void
    {
        $this->editingDate = $date;
        $this->editingSlot = $slot;
        $this->editingPlanId = $planId;

        $hh = auth()->user()->household_id;
        $allMemberIds = FamilyMember::where('household_id', $hh)->pluck('id')->all();

        if ($planId) {
            $plan = MealPlan::with('attendees', 'skippedIngredients')->where('household_id', $hh)->findOrFail($planId);
            $this->selectedRecipeId = $plan->recipe_id;
            $this->selectedLeftoverId = $plan->leftover_of_id;
            $this->customName = $plan->custom_name ?? '';
            $this->notes = $plan->notes ?? '';
            $this->saveLeftovers = $plan->save_leftovers;
            $this->leftoverServings = $plan->leftover_servings;
            $this->attendees = $plan->attendees->pluck('id')->all();
            $this->skippedIngredientIds = $plan->skippedIngredients->pluck('id')->all();
        } else {
            $this->selectedRecipeId = null;
            $this->selectedLeftoverId = null;
            $this->customName = '';
            $this->notes = '';
            $this->saveLeftovers = false;
            $this->leftoverServings = null;
            $unavailableIds = FamilyMemberUnavailability::whereIn('family_member_id', $allMemberIds)
                ->whereDate('date', $date)->where('slot', $slot)
                ->pluck('family_member_id')->all();
            $this->attendees = array_values(array_diff($allMemberIds, $unavailableIds));
            $this->skippedIngredientIds = [];
        }
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingPlanId', 'editingDate', 'editingSlot', 'selectedRecipeId', 'selectedLeftoverId', 'customName', 'notes', 'saveLeftovers', 'leftoverServings', 'attendees', 'skippedIngredientIds']);
    }

    public function savePlan(): void
    {
        $hh = auth()->user()->household_id;

        $data = [
            'household_id' => $hh,
            'date' => $this->editingDate,
            'slot' => $this->editingSlot,
            'recipe_id' => $this->selectedLeftoverId ? null : $this->selectedRecipeId,
            'leftover_of_id' => $this->selectedLeftoverId,
            'custom_name' => $this->customName ?: null,
            'notes' => $this->notes ?: null,
            'save_leftovers' => $this->saveLeftovers,
            'leftover_servings' => $this->saveLeftovers ? $this->leftoverServings : null,
        ];

        if ($this->editingPlanId) {
            $plan = MealPlan::where('household_id', $hh)->findOrFail($this->editingPlanId);
            $plan->update($data);
        } else {
            $plan = MealPlan::create($data);
        }

        $plan->attendees()->sync(
            FamilyMember::where('household_id', $hh)->whereIn('id', $this->attendees)->pluck('id')
        );

        $effectiveRecipeId = $plan->recipe_id ?? $plan->leftoverOf?->recipe_id;
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

    public function render()
    {
        $hh = auth()->user()->household_id;
        $start = CarbonImmutable::parse($this->weekStart);
        $end = $start->addDays(6);

        $days = collect(range(0, 6))->map(fn ($i) => $start->addDays($i));

        $plans = MealPlan::where('household_id', $hh)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('recipe.ingredients', 'attendees.user', 'leftoverOf.recipe.ingredients', 'skippedIngredients')
            ->get()
            ->groupBy(fn ($p) => $p->date->toDateString().'|'.$p->slot);

        $members = FamilyMember::where('household_id', $hh)->with('user')->orderBy('name')->get();
        $recipes = Recipe::where('household_id', $hh)->orderBy('name')->get();

        $unavailabilities = FamilyMemberUnavailability::whereIn('family_member_id', $members->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($u) => $u->date->toDateString().'|'.$u->slot)
            ->map(fn ($group) => $group->pluck('family_member_id')->all());

        $defaultAttendees = [];
        foreach ($days as $d) {
            foreach (['breakfast', 'lunch', 'dinner'] as $slot) {
                $key = $d->toDateString().'|'.$slot;
                $unavailableIds = $unavailabilities->get($key, []);
                $defaultAttendees[$key] = $members->whereNotIn('id', $unavailableIds)->values();
            }
        }

        // Available leftovers: meals from past 3 days where save_leftovers=true and not yet consumed as leftover
        $consumedLeftoverIds = MealPlan::where('household_id', $hh)
            ->whereNotNull('leftover_of_id')
            ->pluck('leftover_of_id')->all();

        $availableLeftovers = MealPlan::where('household_id', $hh)
            ->where('save_leftovers', true)
            ->whereNotIn('id', $consumedLeftoverIds)
            ->whereDate('date', '>=', $start->subDays(3)->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->with('recipe')
            ->get();

        $activeIngredients = collect();
        $activeRecipeServings = 1;
        if ($this->editingDate) {
            $activeRecipeId = $this->selectedRecipeId;
            if (! $activeRecipeId && $this->selectedLeftoverId) {
                $activeRecipeId = MealPlan::where('household_id', $hh)->find($this->selectedLeftoverId)?->recipe_id;
            }
            if ($activeRecipeId) {
                $activeRecipe = Recipe::where('household_id', $hh)->with('ingredients')->find($activeRecipeId);
                if ($activeRecipe) {
                    $activeIngredients = $activeRecipe->ingredients;
                    $activeRecipeServings = max(1, (int) $activeRecipe->servings);
                }
            }
        }

        $today = CarbonImmutable::today(auth()->user()->getTimezone())->toDateString();

        return view('livewire.planner', compact('days', 'plans', 'members', 'recipes', 'availableLeftovers', 'start', 'activeIngredients', 'activeRecipeServings', 'today', 'defaultAttendees'));
    }
}
