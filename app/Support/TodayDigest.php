<?php

namespace App\Support;

use App\Models\MealPlan;
use App\Models\TodoItem;
use App\Models\User;
use Carbon\CarbonImmutable;

class TodayDigest
{
    public const SLOT_ORDER = ['breakfast' => 0, 'lunch' => 1, 'dinner' => 2, 'snack' => 3];

    /**
     * Build the digest payload for a user, anchored to *their* local "today".
     *
     * @return array{
     *   date: CarbonImmutable,
     *   meals: array<int, array{slot: string, name: string, time: ?string}>,
     *   todos: array<int, array{title: string, list: ?string, assigned_to_me: bool}>,
     *   has_content: bool,
     * }
     */
    public static function for(User $user): array
    {
        $tz = $user->getTimezone();
        $today = CarbonImmutable::today($tz);
        $hh = $user->household_id;

        $meals = [];
        $todos = [];

        if ($hh) {
            $meals = MealPlan::where('household_id', $hh)
                ->whereDate('date', $today->toDateString())
                ->with('recipe')
                ->get()
                ->sortBy(fn ($p) => (self::SLOT_ORDER[$p->slot] ?? 99).'-'.$p->id)
                ->map(fn (MealPlan $plan) => [
                    'slot' => ucfirst($plan->slot),
                    'name' => $plan->recipe?->name ?? 'Planned meal',
                    'time' => $plan->start_time ? CarbonImmutable::parse($plan->start_time)->format('g:i a') : null,
                ])
                ->values()
                ->all();

            $member = $user->familyMember;

            $todos = TodoItem::query()
                ->whereHas('list', fn ($q) => $q->where('household_id', $hh))
                ->whereNull('completed_at')
                ->where(function ($q) use ($today) {
                    $q->whereNull('due_date')->orWhereDate('due_date', '<=', $today->toDateString());
                })
                ->with('list', 'assignees')
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->limit(25)
                ->get()
                ->map(fn (TodoItem $item) => [
                    'title' => $item->title,
                    'list' => $item->list?->name,
                    'assigned_to_me' => $member
                        ? $item->assignees->contains(fn ($a) => $a->id === $member->id)
                        : false,
                ])
                ->all();
        }

        return [
            'date' => $today,
            'meals' => $meals,
            'todos' => $todos,
            'has_content' => count($meals) > 0 || count($todos) > 0,
        ];
    }
}
