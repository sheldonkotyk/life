<?php

namespace App\Livewire;

use App\Models\MealPlan;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ShoppingList extends Component
{
    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
    }

    public function shiftWeek(int $weeks): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($weeks)->toDateString();
    }

    public function render()
    {
        $hh = auth()->user()->household_id;
        $start = CarbonImmutable::parse($this->weekStart)->startOfWeek();
        $end = $start->addDays(6);

        $plans = MealPlan::where('household_id', $hh)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('recipe_id')
            ->whereNull('leftover_of_id')
            ->with('recipe.ingredients', 'attendees')
            ->get();

        $grouped = [];
        foreach ($plans as $plan) {
            $eaters = max(1, $plan->attendees->count());
            $servings = $plan->recipe->servings ?: 1;
            $scale = $eaters / $servings;
            foreach ($plan->recipe->ingredients as $ing) {
                $cat = $ing->category ?: 'Other';
                $key = strtolower(trim($ing->name . '|' . ($ing->unit ?? '')));
                if (! isset($grouped[$cat][$key])) {
                    $grouped[$cat][$key] = [
                        'name' => $ing->name,
                        'unit' => $ing->unit,
                        'qty_total' => 0.0,
                        'qty_text' => [],
                        'meals' => [],
                    ];
                }
                $numeric = is_numeric($ing->quantity) ? (float) $ing->quantity : null;
                if ($numeric !== null) {
                    $grouped[$cat][$key]['qty_total'] += $numeric * $scale;
                } elseif ($ing->quantity) {
                    $grouped[$cat][$key]['qty_text'][] = $ing->quantity;
                }
                $grouped[$cat][$key]['meals'][] = $plan->recipe->name;
            }
        }

        ksort($grouped);
        foreach ($grouped as &$items) ksort($items);

        return view('livewire.shopping-list', compact('grouped', 'start', 'plans'));
    }
}
