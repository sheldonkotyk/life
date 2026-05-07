<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\MealPlan;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Tonight extends Component
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

    public function render()
    {
        $user = auth()->user();
        $tz = $user->getTimezone();
        $hh = $user->household_id;
        $today = CarbonImmutable::today($tz);

        $dinner = MealPlan::where('household_id', $hh)
            ->whereDate('date', $today->toDateString())
            ->where('slot', 'dinner')
            ->with('recipe.ingredients', 'attendees', 'leftoverOf.recipe.ingredients', 'skippedIngredients')
            ->first();

        $members = FamilyMember::where('household_id', $hh)
            ->where('is_guest', false)
            ->with('user')
            ->orderBy('name')
            ->get();

        $myMember = $members->firstWhere('user_id', $user->id);

        // Default attendee set if no plan exists yet
        $defaultAttendeeIds = collect();
        if (! $dinner) {
            $unavailableIds = FamilyMemberUnavailability::whereIn('family_member_id', $members->pluck('id'))
                ->whereDate('date', $today->toDateString())
                ->where('slot', 'dinner')
                ->pluck('family_member_id')->all();
            $defaultAttendeeIds = $members
                ->filter(fn ($m) => ! in_array($m->id, $unavailableIds) && $m->attendsByDefault(strtolower($today->format('D')), 'dinner'))
                ->pluck('id');
        }

        // Attendance summary
        $statuses = [];
        $confirmedCount = 0;
        $lateCount = 0;
        if ($dinner) {
            foreach ($dinner->attendees as $a) {
                $s = $a->pivot->status ?? 'eating';
                $statuses[$a->id] = $s;
                if ($s === 'eating') {
                    $confirmedCount++;
                } elseif ($s === 'running_late') {
                    $lateCount++;
                    $confirmedCount++;
                }
            }
        } else {
            $confirmedCount = $defaultAttendeeIds->count();
        }

        $myStatus = $myMember && isset($statuses[$myMember->id]) ? $statuses[$myMember->id] : null;

        // Macros: scale per-serving by confirmed count
        $perServing = $dinner ? $dinner->macrosPerServing() : null;
        $scaledMacros = null;
        if ($perServing && $confirmedCount > 0) {
            $scaledMacros = array_map(fn ($v) => round($v * $confirmedCount, 1), $perServing);
        }

        // Leftover suggestion: any unconsumed save_leftovers from past 3 days, excluding tonight
        $consumedIds = MealPlan::where('household_id', $hh)
            ->whereNotNull('leftover_of_id')
            ->pluck('leftover_of_id')->all();

        $leftovers = MealPlan::where('household_id', $hh)
            ->where('save_leftovers', true)
            ->whereNotIn('id', $consumedIds)
            ->whereDate('date', '>=', $today->subDays(3)->toDateString())
            ->whereDate('date', '<', $today->toDateString())
            ->with('recipe')
            ->orderBy('date', 'desc')
            ->get();

        $recipe = $dinner?->effectiveRecipe();
        $prepMinutes = $recipe?->prep_minutes;

        return view('livewire.tonight', compact(
            'dinner',
            'recipe',
            'prepMinutes',
            'members',
            'myMember',
            'myStatus',
            'statuses',
            'confirmedCount',
            'lateCount',
            'scaledMacros',
            'perServing',
            'defaultAttendeeIds',
            'leftovers',
            'today',
        ));
    }
}
