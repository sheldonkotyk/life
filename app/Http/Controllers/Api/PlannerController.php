<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Support\ApiPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The week grid: what is planned, and who each meal expects.
 */
class PlannerController extends Controller
{
    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->getTimezone();
        $householdId = $user->household_id;

        $start = $this->weekStart($request, $timezone);
        $days = collect(range(0, 6))->map(fn (int $offset) => $start->addDays($offset));
        $end = $start->addDays(6);

        $members = FamilyMember::where('household_id', $householdId)
            ->orderBy('is_guest')
            ->orderBy('name')
            ->get();

        $plans = MealPlan::where('household_id', $householdId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('recipe.ingredients', 'attendees', 'leftoverSources.recipe.ingredients', 'skippedIngredients', 'household')
            ->get();

        $unavailable = FamilyMemberUnavailability::whereIn('family_member_id', $members->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (FamilyMemberUnavailability $row) => $row->date->toDateString().'|'.$row->slot)
            ->map(fn ($group) => $group->pluck('family_member_id')->all());

        $defaults = [];
        foreach ($days as $day) {
            foreach (self::SLOTS as $slot) {
                $key = $day->toDateString().'|'.$slot;
                $rowIds = $unavailable->get($key, []);
                $defaults[$key] = $members
                    ->filter(fn (FamilyMember $member) => $member->is_guest
                        ? in_array($member->id, $rowIds, true)
                        : ! in_array($member->id, $rowIds, true))
                    ->pluck('id')
                    ->values()
                    ->all();
            }
        }

        $memberId = $request->integer('member_id') ?: ($user->familyMember?->id ?? $members->first()?->id);
        $selected = $members->firstWhere('id', $memberId);

        return response()->json([
            'week_start' => $start->toDateString(),
            'days' => $days->map(fn (CarbonImmutable $day) => $day->toDateString())->all(),
            'today' => CarbonImmutable::today($timezone)->toDateString(),
            'slots' => self::SLOTS,
            'member_id' => $selected?->id,
            'members' => $members->map(fn (FamilyMember $member) => ApiPayload::member($member))->all(),
            'selectable_member_ids' => $this->selectableMemberIds($request, $members),
            'plans' => $plans
                ->groupBy(fn (MealPlan $plan) => $plan->date->toDateString().'|'.$plan->slot)
                ->map(fn ($group) => $group->map(fn (MealPlan $plan) => ApiPayload::mealPlan($plan))->values())
                ->all() ?: (object) [],
            'default_attendees' => (object) $defaults,
            'not_attending_keys' => $selected ? $this->notAttendingKeys($selected, $days) : [],
            'recipes' => Recipe::where('household_id', $householdId)
                ->orderBy('name')
                ->get()
                ->map(fn (Recipe $recipe) => [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'servings' => $recipe->servings,
                    'prep_minutes' => $recipe->prep_minutes,
                    'makes_leftovers' => (bool) $recipe->makes_leftovers,
                    'default_leftover_servings' => $recipe->default_leftover_servings,
                ])
                ->all(),
            'available_leftovers' => $this->availableLeftovers($householdId, $start, $end),
        ]);
    }

    /**
     * Mark a member in or out of one or more meals for a specific date.
     *
     * Household members are assumed in, so a row means "away"; guests are
     * assumed out, so for them the same row means "coming". One table, read in
     * opposite directions, which is why this cannot be a plain boolean column.
     */
    public function setAttendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'family_member_id' => ['required', 'integer'],
            'attending' => ['required', 'boolean'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.date' => ['required', 'date'],
            'slots.*.slot' => ['required', 'in:breakfast,lunch,dinner'],
        ]);

        $member = FamilyMember::where('household_id', $request->user()->household_id)
            ->findOrFail($data['family_member_id']);

        $attending = $data['attending'];
        $shouldHaveRow = $member->is_guest ? $attending : ! $attending;

        foreach ($data['slots'] as $pair) {
            $date = CarbonImmutable::parse($pair['date'])->toDateString();

            if ($shouldHaveRow) {
                FamilyMemberUnavailability::firstOrCreate([
                    'family_member_id' => $member->id,
                    'date' => $date,
                    'slot' => $pair['slot'],
                ]);
            } else {
                FamilyMemberUnavailability::where('family_member_id', $member->id)
                    ->whereDate('date', $date)
                    ->where('slot', $pair['slot'])
                    ->delete();
            }

            if (! $attending) {
                MealPlan::where('household_id', $member->household_id)
                    ->whereDate('date', $date)
                    ->where('slot', $pair['slot'])
                    ->each(fn (MealPlan $plan) => $plan->attendees()->detach($member->id));
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableLeftovers(int $householdId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $consumedIds = DB::table('meal_plan_leftover_uses')->pluck('source_meal_plan_id')->all();

        return MealPlan::where('household_id', $householdId)
            ->where('save_leftovers', true)
            ->whereNotIn('id', $consumedIds)
            ->whereDate('date', '>=', $start->subDays(3)->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->with('recipe', 'leftoverSources.recipe')
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
     * @param  Collection<int, CarbonImmutable>  $days
     * @return list<string>
     */
    private function notAttendingKeys(FamilyMember $member, $days): array
    {
        $overrides = FamilyMemberUnavailability::where('family_member_id', $member->id)
            ->whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->get()
            ->map(fn (FamilyMemberUnavailability $row) => $row->date->toDateString().'|'.$row->slot)
            ->all();

        if (! $member->is_guest) {
            return array_values($overrides);
        }

        $all = [];
        foreach ($days as $day) {
            foreach (self::SLOTS as $slot) {
                $all[] = $day->toDateString().'|'.$slot;
            }
        }

        return array_values(array_diff($all, $overrides));
    }

    /**
     * @param  Collection<int, FamilyMember>  $members
     * @return list<int>
     */
    private function selectableMemberIds(Request $request, $members): array
    {
        $user = $request->user();

        if ($user->household && $user->isAdminOf($user->household)) {
            return $members->pluck('id')->values()->all();
        }

        $ownId = $user->familyMember?->id;

        return $members
            ->filter(fn (FamilyMember $member) => $member->is_guest || $member->id === $ownId)
            ->pluck('id')
            ->values()
            ->all();
    }

    private function weekStart(Request $request, string $timezone): CarbonImmutable
    {
        $value = $request->query('week_start');

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::parse($value, $timezone)->startOfDay();
        }

        return CarbonImmutable::today($timezone);
    }
}
