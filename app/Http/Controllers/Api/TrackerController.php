<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Support\ApiPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What each person actually ate on a day, against whatever they are aiming for.
 */
class TrackerController extends Controller
{
    private const SLOT_ORDER = ['breakfast', 'lunch', 'dinner', 'snack'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->getTimezone();
        $date = $this->date($request, $timezone);

        $members = FamilyMember::where('household_id', $user->household_id)
            ->visible()
            ->orderBy('is_child')
            ->orderBy('name')
            ->get();

        $plans = MealPlan::where('household_id', $user->household_id)
            ->whereDate('date', $date->toDateString())
            ->with('recipe.ingredients', 'leftoverSources.recipe.ingredients', 'skippedIngredients', 'attendees')
            ->get()
            ->sortBy(fn (MealPlan $plan) => array_search($plan->slot, self::SLOT_ORDER, true))
            ->values();

        $empty = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];
        $consumed = [];
        $eaten = [];

        foreach ($members as $member) {
            $consumed[$member->id] = $empty;
            $eaten[$member->id] = [];
        }

        foreach ($plans as $plan) {
            $macros = $plan->macrosPerServing();

            foreach ($plan->attendees as $attendee) {
                if (! isset($consumed[$attendee->id])) {
                    continue;
                }

                foreach ($macros as $key => $value) {
                    $consumed[$attendee->id][$key] += $value;
                }

                $eaten[$attendee->id][] = [
                    'meal_plan_id' => $plan->id,
                    'name' => $plan->displayName(),
                    'slot' => $plan->slot,
                    'macros' => $macros,
                ];
            }
        }

        return response()->json([
            'date' => $date->toDateString(),
            'members' => $members->map(fn (FamilyMember $member) => [
                ...ApiPayload::member($member),
                'consumed' => array_map(fn ($value) => round($value, 1), $consumed[$member->id]),
                'meals' => $eaten[$member->id],
            ])->all(),
        ]);
    }

    private function date(Request $request, string $timezone): CarbonImmutable
    {
        $value = $request->query('date');

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::parse($value, $timezone)->startOfDay();
        }

        return CarbonImmutable::today($timezone);
    }
}
