<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\TodoItem;
use App\Services\DayAgenda;
use App\Support\ApiPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one screen the app opens on: today's meals, today's jobs, today's calendar.
 */
class TodayController extends Controller
{
    private const SLOT_ORDER = ['breakfast' => 0, 'lunch' => 1, 'dinner' => 2, 'snack' => 3];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->getTimezone();
        $householdId = $user->household_id;
        $day = $this->day($request, $timezone);

        $plans = MealPlan::where('household_id', $householdId)
            ->whereDate('date', $day->toDateString())
            ->with('recipe.ingredients', 'attendees', 'leftoverSources.recipe.ingredients', 'skippedIngredients', 'household')
            ->get()
            ->sortBy(fn (MealPlan $plan) => (self::SLOT_ORDER[$plan->slot] ?? 99).'-'.$plan->id)
            ->values();

        $members = FamilyMember::where('household_id', $householdId)
            ->where('is_guest', false)
            ->orderBy('name')
            ->get();

        $myMember = $members->firstWhere('user_id', $user->id);

        $plannedSlots = $plans->pluck('slot')->unique()->all();
        $unplannedSlots = collect(['breakfast', 'lunch', 'dinner'])
            ->reject(fn (string $slot) => in_array($slot, $plannedSlots, true))
            ->values()
            ->all();

        return response()->json([
            'date' => $day->toDateString(),
            'timezone' => $timezone,
            'my_family_member_id' => $myMember?->id,
            'members' => $members->map(fn (FamilyMember $member) => ApiPayload::member($member))->all(),
            'meals' => $plans->map(fn (MealPlan $plan) => ApiPayload::mealPlan($plan))->all(),
            'unplanned_slots' => $unplannedSlots,
            'leftovers' => $this->leftovers($householdId, $day),
            'todos' => $this->todos($householdId, $day, $myMember)
                ->map(fn (TodoItem $item) => ApiPayload::todoItem($item))
                ->all(),
            'agenda' => app(DayAgenda::class)->forUser($user, $day, $timezone),
        ]);
    }

    /**
     * Leftovers still worth eating: saved in the last three days, not yet claimed.
     *
     * @return list<array<string, mixed>>
     */
    private function leftovers(int $householdId, CarbonImmutable $day): array
    {
        $consumedIds = DB::table('meal_plan_leftover_uses')->pluck('source_meal_plan_id')->all();

        return MealPlan::where('household_id', $householdId)
            ->where('save_leftovers', true)
            ->whereNotIn('id', $consumedIds)
            ->whereDate('date', '>=', $day->subDays(3)->toDateString())
            ->whereDate('date', '<', $day->toDateString())
            ->with('recipe', 'leftoverSources.recipe')
            ->orderByDesc('date')
            ->get()
            ->map(fn (MealPlan $plan) => [
                'id' => $plan->id,
                'date' => $plan->date->toDateString(),
                'slot' => $plan->slot,
                'name' => $plan->displayName(),
                'servings' => $plan->leftover_servings,
            ])
            ->all();
    }

    /**
     * Anything due by today, plus everything assigned to me whenever it is due.
     *
     * @return Collection<int, TodoItem>
     */
    private function todos(int $householdId, CarbonImmutable $day, ?FamilyMember $myMember)
    {
        return TodoItem::query()
            ->whereHas('list', fn ($query) => $query->where('household_id', $householdId))
            ->whereNull('completed_at')
            ->where(function ($query) use ($day, $myMember) {
                $query->whereDate('due_date', '<=', $day->toDateString());
                if ($myMember) {
                    $query->orWhereHas('assignees', fn ($a) => $a->where('family_members.id', $myMember->id));
                }
            })
            ->with('list', 'assignees')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderBy('id')
            ->get();
    }

    private function day(Request $request, string $timezone): CarbonImmutable
    {
        $date = $request->query('date');

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return CarbonImmutable::parse($date, $timezone)->startOfDay();
        }

        return CarbonImmutable::today($timezone);
    }
}
