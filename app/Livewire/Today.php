<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\TodoItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Today extends Component
{
    public const STATUSES = ['eating', 'running_late', 'not_eating'];

    public function setMyStatus(int $planId, string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            return;
        }

        $user = auth()->user();
        $hh = $user->household_id;

        $member = FamilyMember::where('household_id', $hh)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            return;
        }

        $this->setMemberStatus($planId, $member->id, $status);
    }

    public function setMemberStatus(int $planId, int $memberId, string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            return;
        }

        $hh = auth()->user()->household_id;
        $plan = MealPlan::where('household_id', $hh)->findOrFail($planId);
        $member = FamilyMember::where('household_id', $hh)->findOrFail($memberId);

        $plan->attendees()->syncWithoutDetaching([$member->id => ['status' => $status]]);
    }

    public function toggleTodo(int $itemId): void
    {
        $hh = auth()->user()->household_id;
        $item = TodoItem::with('list')->findOrFail($itemId);
        if (! $item->list || $item->list->household_id !== $hh) {
            abort(403);
        }

        if ($item->completed_at) {
            $item->update(['completed_at' => null, 'completed_by_family_member_id' => null]);

            return;
        }

        $member = auth()->user()->familyMember;
        $item->update([
            'completed_at' => CarbonImmutable::now(),
            'completed_by_family_member_id' => $member?->id,
        ]);

        if ($item->isRecurring()) {
            $item->spawnNextOccurrence();
        }
    }

    public function render()
    {
        $user = auth()->user();
        $tz = $user->getTimezone();
        $hh = $user->household_id;
        $today = CarbonImmutable::today($tz);

        $slotOrder = ['breakfast' => 0, 'lunch' => 1, 'dinner' => 2, 'snack' => 3];

        $plans = MealPlan::where('household_id', $hh)
            ->whereDate('date', $today->toDateString())
            ->with('recipe.ingredients', 'attendees', 'leftoverSources.recipe.ingredients', 'skippedIngredients')
            ->get()
            ->sortBy(fn ($p) => ($slotOrder[$p->slot] ?? 99).'-'.$p->id)
            ->values();

        $members = FamilyMember::where('household_id', $hh)
            ->where('is_guest', false)
            ->with('user')
            ->orderBy('name')
            ->get();

        $myMember = $members->firstWhere('user_id', $user->id);

        $meals = $plans->map(function ($plan) {
            $statuses = [];
            $confirmedCount = 0;
            $lateCount = 0;
            foreach ($plan->attendees as $a) {
                $s = $a->pivot->status ?? 'eating';
                $statuses[$a->id] = $s;
                if ($s === 'eating') {
                    $confirmedCount++;
                } elseif ($s === 'running_late') {
                    $lateCount++;
                    $confirmedCount++;
                }
            }

            $perServing = $plan->macrosPerServing();
            $scaledMacros = null;
            if ($perServing && $confirmedCount > 0) {
                $scaledMacros = array_map(fn ($v) => round($v * $confirmedCount, 1), $perServing);
            }

            $recipe = $plan->effectiveRecipe();

            return [
                'plan' => $plan,
                'recipe' => $recipe,
                'prepMinutes' => $recipe?->prep_minutes,
                'statuses' => $statuses,
                'confirmedCount' => $confirmedCount,
                'lateCount' => $lateCount,
                'perServing' => $perServing,
                'scaledMacros' => $scaledMacros,
            ];
        });

        // Default attendee set for slots with no plan (used by the empty state)
        $plannedSlots = $plans->pluck('slot')->unique()->all();
        $unplannedSlots = collect(['breakfast', 'lunch', 'dinner'])
            ->reject(fn ($s) => in_array($s, $plannedSlots, true))
            ->values();

        // Leftover suggestion: any unconsumed save_leftovers from past 3 days, excluding today
        $consumedIds = DB::table('meal_plan_leftover_uses')
            ->pluck('source_meal_plan_id')->all();

        $leftovers = MealPlan::where('household_id', $hh)
            ->where('save_leftovers', true)
            ->whereNotIn('id', $consumedIds)
            ->whereDate('date', '>=', $today->subDays(3)->toDateString())
            ->whereDate('date', '<', $today->toDateString())
            ->with('recipe')
            ->orderBy('date', 'desc')
            ->get();

        $todayDate = $today->toDateString();
        $todoQuery = TodoItem::query()
            ->whereHas('list', fn ($q) => $q->where('household_id', $hh))
            ->whereNull('completed_at')
            ->where(function ($q) use ($todayDate, $myMember) {
                $q->whereDate('due_date', '<=', $todayDate);
                if ($myMember) {
                    $q->orWhereHas('assignees', fn ($a) => $a->where('family_members.id', $myMember->id));
                }
            })
            ->with(['list', 'assignees'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderBy('id');

        $todos = $todoQuery->get();

        return view('livewire.today', compact(
            'meals',
            'members',
            'myMember',
            'unplannedSlots',
            'leftovers',
            'today',
            'todos',
        ));
    }
}
