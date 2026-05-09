<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TodoItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'recurrence_until' => 'date',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'todo_list_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'completed_by_family_member_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'todo_item_assignments')->withTimestamps();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_frequency !== null;
    }

    public function nextDueDate(): ?CarbonImmutable
    {
        if (! $this->isRecurring()) {
            return null;
        }

        $base = $this->due_date
            ? CarbonImmutable::parse($this->due_date)
            : CarbonImmutable::now();
        $interval = max(1, (int) ($this->recurrence_interval ?? 1));

        $next = match ($this->recurrence_frequency) {
            'daily' => $base->addDays($interval),
            'weekly' => $base->addWeeks($interval),
            'monthly' => $base->addMonthsNoOverflow($interval),
            'yearly' => $base->addYearsNoOverflow($interval),
            default => null,
        };

        if ($next === null) {
            return null;
        }

        if ($this->recurrence_until && $next->isAfter(CarbonImmutable::parse($this->recurrence_until))) {
            return null;
        }

        return $next;
    }

    public function spawnNextOccurrence(): ?self
    {
        $next = $this->nextDueDate();
        if ($next === null) {
            return null;
        }

        $clone = $this->replicate(['completed_at', 'completed_by_family_member_id']);
        $clone->due_date = $next->toDateString();
        $clone->completed_at = null;
        $clone->completed_by_family_member_id = null;
        $clone->save();

        $clone->assignees()->sync($this->assignees()->pluck('family_members.id')->all());

        return $clone;
    }
}
