<?php

namespace App\Support;

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\MealPlan;
use App\Models\TodoItem;
use App\Models\TodoList;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Shapes the mobile app reads.
 *
 * The web app hands whole Eloquent models to Blade, which is free to reach for
 * another relation mid-render. A phone cannot, so every screen endpoint answers
 * with a flat, explicit shape built here — one place to change when the client
 * needs another field.
 */
class ApiPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function user(User $user): array
    {
        $stored = $user->getRawOriginal('avatar');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'household_id' => $user->household_id,
            'family_member_id' => $user->familyMember?->id,
            'timezone' => $user->getTimezone(),
            'birthday' => $user->birthday?->toDateString(),
            'avatar_config' => $user->avatar_config,
            // The built avatar is drawn by the client from its config, so only a
            // real uploaded or remote picture becomes a URL here.
            'avatar_url' => $stored
                ? (str_starts_with($stored, 'http') ? $stored : Storage::disk('public')->url($stored))
                : null,
            'notification_preferences' => $user->notificationPreferences(),
            'daily_today_email_at' => $user->daily_today_email_at
                ? mb_substr((string) $user->daily_today_email_at, 0, 5)
                : null,
            'daily_today_email_enabled' => (bool) $user->daily_today_email_enabled,
            'booking_emails_enabled' => (bool) $user->booking_emails_enabled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function household(Household $household, ?User $forUser = null): array
    {
        return [
            'id' => $household->id,
            'name' => $household->name,
            'invite_code' => $household->invite_code,
            'is_current' => $forUser?->household_id === $household->id,
            'is_admin' => $forUser ? $forUser->isAdminOf($household) : null,
            'can_manage' => $forUser ? $forUser->canManageHousehold($household) : null,
            'meal_times' => [
                'breakfast' => [
                    'start' => mb_substr((string) $household->breakfast_start_time, 0, 5),
                    'end' => mb_substr((string) $household->breakfast_end_time, 0, 5),
                ],
                'lunch' => [
                    'start' => mb_substr((string) $household->lunch_start_time, 0, 5),
                    'end' => mb_substr((string) $household->lunch_end_time, 0, 5),
                ],
                'dinner' => [
                    'start' => mb_substr((string) $household->dinner_start_time, 0, 5),
                    'end' => mb_substr((string) $household->dinner_end_time, 0, 5),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function member(FamilyMember $member): array
    {
        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'name' => $member->name,
            'color' => $member->color,
            'is_child' => (bool) $member->is_child,
            'is_guest' => (bool) $member->is_guest,
            'birthday' => $member->birthday?->toDateString(),
            'notes' => $member->notes,
            'avatar_config' => $member->avatar_config,
            'default_attendance' => $member->default_attendance,
            'target_calories' => $member->target_calories,
            'target_protein_g' => $member->target_protein_g,
            'target_carbs_g' => $member->target_carbs_g,
            'target_fat_g' => $member->target_fat_g,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mealPlan(MealPlan $plan): array
    {
        $statuses = [];
        $confirmed = 0;
        $late = 0;

        foreach ($plan->attendees as $attendee) {
            $status = $attendee->pivot->status ?? 'eating';
            $statuses[(string) $attendee->id] = $status;

            if ($status === 'eating') {
                $confirmed++;
            } elseif ($status === 'running_late') {
                $late++;
                $confirmed++;
            }
        }

        $perServing = $plan->macrosPerServing();
        $recipe = $plan->effectiveRecipe();

        return [
            'id' => $plan->id,
            'date' => $plan->date->toDateString(),
            'slot' => $plan->slot,
            'recipe_id' => $plan->recipe_id,
            'custom_name' => $plan->custom_name,
            'display_name' => $plan->displayName(),
            'notes' => $plan->notes,
            'save_leftovers' => (bool) $plan->save_leftovers,
            'leftover_servings' => $plan->leftover_servings,
            'leftover_source_ids' => $plan->relationLoaded('leftoverSources')
                ? $plan->leftoverSources->pluck('id')->values()->all()
                : [],
            'skipped_ingredient_ids' => $plan->relationLoaded('skippedIngredients')
                ? $plan->skippedIngredients->pluck('id')->values()->all()
                : [],
            'start_time' => $plan->effectiveStartTime(),
            'end_time' => $plan->effectiveEndTime(),
            'own_start_time' => $plan->start_time ? mb_substr($plan->start_time, 0, 5) : null,
            'own_end_time' => $plan->end_time ? mb_substr($plan->end_time, 0, 5) : null,
            'prep_minutes' => $recipe?->prep_minutes,
            'statuses' => (object) $statuses,
            'attendee_ids' => $plan->attendees->pluck('id')->values()->all(),
            'confirmed_count' => $confirmed,
            'late_count' => $late,
            'per_serving' => $perServing,
            'scaled_macros' => $confirmed > 0
                ? array_map(fn ($value) => round($value * $confirmed, 1), $perServing)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function todoItem(TodoItem $item): array
    {
        return [
            'id' => $item->id,
            'todo_list_id' => $item->todo_list_id,
            'list_name' => $item->relationLoaded('list') ? $item->list?->name : null,
            'list_color' => $item->relationLoaded('list') ? $item->list?->color : null,
            'title' => $item->title,
            'notes' => $item->notes,
            'due_date' => $item->due_date?->toDateString(),
            'completed_at' => $item->completed_at?->toIso8601String(),
            'completed_by_family_member_id' => $item->completed_by_family_member_id,
            'position' => $item->position,
            'recurrence_frequency' => $item->recurrence_frequency,
            'recurrence_interval' => $item->recurrence_interval,
            'recurrence_until' => $item->recurrence_until?->toDateString(),
            'assignee_ids' => $item->relationLoaded('assignees')
                ? $item->assignees->pluck('id')->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function todoList(TodoList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'color' => $list->color,
            'description' => $list->description,
            'position' => $list->position,
            'open_count' => (int) ($list->open_count ?? 0),
        ];
    }
}
