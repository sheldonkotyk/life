<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\RecipeIngredient;
use App\Support\ApiPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hh = $request->user()->household_id;
        $start = $request->date('from') ?? CarbonImmutable::now($request->user()->getTimezone())->startOfWeek();
        $end = $request->date('to') ?? CarbonImmutable::parse($start)->addDays(6);

        $plans = MealPlan::where('household_id', $hh)
            ->whereBetween('date', [$start, $end])
            ->with('recipe.ingredients', 'attendees', 'leftoverSources.recipe.ingredients', 'skippedIngredients', 'household')
            ->orderBy('date')->orderBy('slot')
            ->get();

        return response()->json($plans->map(fn (MealPlan $plan) => ApiPayload::mealPlan($plan)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['household_id'] = $request->user()->household_id;
        $attendees = $data['attendee_ids'] ?? [];
        $leftoverIds = $data['leftover_source_ids'] ?? [];
        $skipped = $data['skipped_ingredient_ids'] ?? [];
        unset($data['attendee_ids'], $data['leftover_source_ids'], $data['skipped_ingredient_ids']);

        $plan = MealPlan::create($data);
        $plan->attendees()->sync($this->attendanceSync($request, $attendees, null));
        $plan->leftoverSources()->sync($this->validLeftoverSources($request, $leftoverIds, $plan->id));
        $plan->skippedIngredients()->sync($this->validSkippedIngredients($plan, $skipped));

        return response()->json(ApiPayload::mealPlan($this->reload($plan)), 201);
    }

    public function update(Request $request, MealPlan $plan): JsonResponse
    {
        $this->authorize($request, $plan);
        $data = $this->validateData($request);
        $attendees = $data['attendee_ids'] ?? null;
        $leftoverIds = $data['leftover_source_ids'] ?? null;
        $skipped = $data['skipped_ingredient_ids'] ?? null;
        unset($data['attendee_ids'], $data['leftover_source_ids'], $data['skipped_ingredient_ids']);

        $plan->update($data);
        if ($attendees !== null) {
            $plan->attendees()->sync($this->attendanceSync($request, $attendees, $plan));
        }
        if ($leftoverIds !== null) {
            $plan->leftoverSources()->sync($this->validLeftoverSources($request, $leftoverIds, $plan->id));
        }
        if ($skipped !== null) {
            $plan->skippedIngredients()->sync($this->validSkippedIngredients($plan, $skipped));
        }

        return response()->json(ApiPayload::mealPlan($this->reload($plan)));
    }

    public function destroy(Request $request, MealPlan $plan): JsonResponse
    {
        $this->authorize($request, $plan);
        $plan->delete();

        return response()->json(['ok' => true]);
    }

    public function shoppingList(Request $request): JsonResponse
    {
        $hh = $request->user()->household_id;
        $start = $request->date('from') ?? CarbonImmutable::now($request->user()->getTimezone())->startOfWeek();
        $end = $request->date('to') ?? CarbonImmutable::parse($start)->addDays(6);

        $plans = MealPlan::where('household_id', $hh)
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('recipe_id')
            ->whereDoesntHave('leftoverSources')
            ->with('recipe.ingredients', 'attendees')
            ->get();

        $list = [];
        foreach ($plans as $plan) {
            $eaters = max(1, $plan->attendees->count());
            $servings = $plan->recipe->servings ?: 1;
            $scale = $eaters / $servings;
            foreach ($plan->recipe->ingredients as $ing) {
                $key = strtolower(($ing->category ?: 'Other').'|'.$ing->name.'|'.($ing->unit ?? ''));
                $list[$key] ??= [
                    'name' => $ing->name,
                    'unit' => $ing->unit,
                    'category' => $ing->category ?: 'Other',
                    'quantity' => 0.0,
                    'notes' => [],
                    'meals' => [],
                ];
                if (is_numeric($ing->quantity)) {
                    $list[$key]['quantity'] += (float) $ing->quantity * $scale;
                } elseif ($ing->quantity) {
                    $list[$key]['notes'][] = $ing->quantity;
                }
                $list[$key]['meals'][] = $plan->recipe->name;
            }
        }

        return response()->json(array_values(array_map(function ($i) {
            $i['meals'] = array_values(array_unique($i['meals']));
            $i['notes'] = array_values(array_unique($i['notes']));

            return $i;
        }, $list)));
    }

    /**
     * Say who is eating without touching the rest of the plan.
     *
     * This is the one write the Today screen makes, so it stays a single small
     * request rather than a read-modify-write of the whole meal.
     */
    public function setAttendance(Request $request, MealPlan $plan): JsonResponse
    {
        $this->authorize($request, $plan);

        $data = $request->validate([
            'family_member_id' => ['nullable', 'integer'],
            'status' => ['required', 'in:eating,running_late,not_eating'],
        ]);

        $memberId = $data['family_member_id'] ?? $request->user()->familyMember?->id;
        abort_unless($memberId, 422);

        $member = FamilyMember::where('household_id', $request->user()->household_id)
            ->findOrFail($memberId);

        $plan->attendees()->syncWithoutDetaching([$member->id => ['status' => $data['status']]]);

        return response()->json(ApiPayload::mealPlan($this->reload($plan)));
    }

    /**
     * Drag a meal to another day or slot. Any explicit time it carried belonged
     * to the old slot, so it is dropped back to the household default.
     */
    public function move(Request $request, MealPlan $plan): JsonResponse
    {
        $this->authorize($request, $plan);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'slot' => ['required', 'in:breakfast,lunch,dinner,snack'],
        ]);

        $plan->update([
            'date' => CarbonImmutable::parse($data['date'])->toDateString(),
            'slot' => $data['slot'],
            'start_time' => null,
            'end_time' => null,
        ]);

        return response()->json(ApiPayload::mealPlan($this->reload($plan)));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'slot' => ['required', 'in:breakfast,lunch,dinner,snack'],
            'recipe_id' => ['nullable', 'exists:recipes,id'],
            'leftover_source_ids' => ['nullable', 'array'],
            'leftover_source_ids.*' => ['integer', 'exists:meal_plans,id'],
            'custom_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'save_leftovers' => ['boolean'],
            'leftover_servings' => ['nullable', 'integer', 'min:0'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer'],
            'skipped_ingredient_ids' => ['nullable', 'array'],
            'skipped_ingredient_ids.*' => ['integer'],
        ]);
    }

    private function reload(MealPlan $plan): MealPlan
    {
        return $plan->fresh([
            'recipe.ingredients',
            'attendees',
            'leftoverSources.recipe.ingredients',
            'skippedIngredients',
            'household',
        ]);
    }

    /**
     * Keep anyone already marked as skipping marked as skipping: the planner
     * sends who is invited, not who has since said no.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array{status: string}>
     */
    private function attendanceSync(Request $request, array $ids, ?MealPlan $plan): array
    {
        $skipping = $plan
            ? $plan->attendees()->wherePivot('status', 'not_eating')->pluck('family_members.id')->all()
            : [];

        $attending = FamilyMember::where('household_id', $request->user()->household_id)
            ->whereIn('id', $ids)
            ->whereNotIn('id', $skipping)
            ->pluck('id')
            ->all();

        return array_fill_keys($attending, ['status' => 'eating'])
            + array_fill_keys($skipping, ['status' => 'not_eating']);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function validSkippedIngredients(MealPlan $plan, array $ids): array
    {
        $recipeId = $plan->recipe_id ?? $plan->leftoverSources()->first()?->recipe_id;

        if (! $recipeId) {
            return [];
        }

        return RecipeIngredient::where('recipe_id', $recipeId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    private function validLeftoverSources(Request $request, array $ids, int $excludeId): array
    {
        return MealPlan::where('household_id', $request->user()->household_id)
            ->whereIn('id', $ids)
            ->where('id', '!=', $excludeId)
            ->pluck('id')->all();
    }

    private function authorize(Request $request, MealPlan $plan): void
    {
        abort_unless($plan->household_id === $request->user()->household_id, 403);
    }
}
