<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\MealPlan;
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
            ->with('recipe', 'attendees', 'leftoverSources.recipe')
            ->orderBy('date')->orderBy('slot')
            ->get();

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['household_id'] = $request->user()->household_id;
        $attendees = $data['attendee_ids'] ?? [];
        $leftoverIds = $data['leftover_source_ids'] ?? [];
        unset($data['attendee_ids'], $data['leftover_source_ids']);

        $plan = MealPlan::create($data);
        $plan->attendees()->sync($this->validAttendees($request, $attendees));
        $plan->leftoverSources()->sync($this->validLeftoverSources($request, $leftoverIds, $plan->id));

        return response()->json($plan->load('recipe', 'attendees', 'leftoverSources'), 201);
    }

    public function update(Request $request, MealPlan $plan): JsonResponse
    {
        $this->authorize($request, $plan);
        $data = $this->validateData($request);
        $attendees = $data['attendee_ids'] ?? null;
        $leftoverIds = $data['leftover_source_ids'] ?? null;
        unset($data['attendee_ids'], $data['leftover_source_ids']);

        $plan->update($data);
        if ($attendees !== null) {
            $plan->attendees()->sync($this->validAttendees($request, $attendees));
        }
        if ($leftoverIds !== null) {
            $plan->leftoverSources()->sync($this->validLeftoverSources($request, $leftoverIds, $plan->id));
        }

        return response()->json($plan->load('recipe', 'attendees', 'leftoverSources'));
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
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer'],
        ]);
    }

    private function validLeftoverSources(Request $request, array $ids, int $excludeId): array
    {
        return MealPlan::where('household_id', $request->user()->household_id)
            ->whereIn('id', $ids)
            ->where('id', '!=', $excludeId)
            ->pluck('id')->all();
    }

    private function validAttendees(Request $request, array $ids): array
    {
        return FamilyMember::where('household_id', $request->user()->household_id)
            ->whereIn('id', $ids)->pluck('id')->all();
    }

    private function authorize(Request $request, MealPlan $plan): void
    {
        abort_unless($plan->household_id === $request->user()->household_id, 403);
    }
}
