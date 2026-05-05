<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\MealPlan;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Tracker extends Component
{
    public string $date;

    public function mount(): void
    {
        $this->date = CarbonImmutable::now()->toDateString();
    }

    public function shiftDay(int $days): void
    {
        $this->date = CarbonImmutable::parse($this->date)->addDays($days)->toDateString();
    }

    public function jumpToToday(): void
    {
        $this->date = CarbonImmutable::now()->toDateString();
    }

    public function render()
    {
        $hh = auth()->user()->household_id;

        $members = FamilyMember::where('household_id', $hh)
            ->orderBy('is_child')
            ->orderBy('name')
            ->get();

        $plans = MealPlan::where('household_id', $hh)
            ->whereDate('date', $this->date)
            ->with('recipe.ingredients', 'leftoverOf.recipe.ingredients', 'skippedIngredients', 'attendees')
            ->get()
            ->sortBy(fn($p) => array_search($p->slot, ['breakfast', 'lunch', 'dinner', 'snack']))
            ->values();

        $consumed = [];
        $perMemberMeals = [];
        foreach ($members as $m) {
            $consumed[$m->id] = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];
            $perMemberMeals[$m->id] = [];
        }

        foreach ($plans as $plan) {
            $macros = $plan->macrosPerServing();
            foreach ($plan->attendees as $att) {
                if (! isset($consumed[$att->id])) continue;
                foreach ($macros as $k => $v) $consumed[$att->id][$k] += $v;
                $perMemberMeals[$att->id][] = [
                    'name' => $plan->displayName(),
                    'slot' => $plan->slot,
                    'macros' => $macros,
                ];
            }
        }

        return view('livewire.tracker', [
            'members' => $members,
            'consumed' => $consumed,
            'perMemberMeals' => $perMemberMeals,
            'displayDate' => CarbonImmutable::parse($this->date),
        ]);
    }
}
