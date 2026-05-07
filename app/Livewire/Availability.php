<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\MealPlan;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Availability extends Component
{
    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    public ?int $memberId = null;

    public string $weekStart;

    public function mount(): void
    {
        $this->memberId = auth()->user()->familyMember?->id
            ?? FamilyMember::where('household_id', auth()->user()->household_id)->value('id');
        $this->weekStart = CarbonImmutable::today(auth()->user()->getTimezone())->toDateString();
    }

    public function shiftWeek(int $weeks): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($weeks)->toDateString();
        unset($this->notAttendingKeys);
    }

    public function jumpToToday(): void
    {
        $this->weekStart = CarbonImmutable::today(auth()->user()->getTimezone())->toDateString();
        unset($this->notAttendingKeys);
    }

    #[Computed]
    public function days(): array
    {
        $start = CarbonImmutable::parse($this->weekStart);

        return array_map(fn ($i) => $start->addDays($i), range(0, 6));
    }

    #[Computed]
    public function members()
    {
        $user = auth()->user();
        $query = FamilyMember::where('household_id', $user->household_id);

        if (! $user->isAdminOf($user->household)) {
            $ownId = $user->familyMember?->id;
            $query->where(fn ($q) => $q->where('is_guest', true)->when($ownId, fn ($q2) => $q2->orWhere('id', $ownId)));
        }

        return $query->orderBy('is_guest')->orderBy('name')->get();
    }

    #[Computed]
    public function selectedMember(): ?FamilyMember
    {
        return $this->members->firstWhere('id', $this->memberId);
    }

    #[Computed]
    public function notAttendingKeys(): array
    {
        if (! $this->memberId) {
            return [];
        }

        $weekStart = CarbonImmutable::parse($this->weekStart);
        $isGuest = (bool) $this->selectedMember?->is_guest;

        $overrideKeys = FamilyMemberUnavailability::where('family_member_id', $this->memberId)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->addDays(6)->toDateString()])
            ->get()
            ->map(fn ($u) => $u->date->toDateString().'|'.$u->slot)
            ->all();

        if (! $isGuest) {
            return $overrideKeys;
        }

        $allKeys = [];
        foreach ($this->days() as $d) {
            foreach (self::SLOTS as $slot) {
                $allKeys[] = $d->toDateString().'|'.$slot;
            }
        }

        return array_values(array_diff($allKeys, $overrideKeys));
    }

    public function setAttending(string $date, string $slot, bool $attend): void
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }
        $this->guardMember();
        $this->applyAttendance([[$date, $slot]], $attend);
    }

    public function setSlotAttending(string $slot, bool $attend): void
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }
        $this->guardMember();

        $pairs = array_map(fn ($d) => [$d->toDateString(), $slot], $this->days());
        $this->applyAttendance($pairs, $attend);
    }

    public function setDayAttending(string $date, bool $attend): void
    {
        $this->guardMember();

        $pairs = array_map(fn ($slot) => [$date, $slot], self::SLOTS);
        $this->applyAttendance($pairs, $attend);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $pairs
     */
    private function applyAttendance(array $pairs, bool $attend): void
    {
        $isGuest = (bool) $this->selectedMember?->is_guest;
        $shouldHaveRow = $isGuest ? $attend : ! $attend;

        foreach ($pairs as [$date, $slot]) {
            if ($shouldHaveRow) {
                FamilyMemberUnavailability::firstOrCreate([
                    'family_member_id' => $this->memberId,
                    'date' => $date,
                    'slot' => $slot,
                ]);
            } else {
                FamilyMemberUnavailability::where('family_member_id', $this->memberId)
                    ->whereDate('date', $date)->where('slot', $slot)->delete();
            }

            if (! $attend) {
                $this->detachExistingMealAttendance($date, $slot);
            }
        }

        unset($this->notAttendingKeys);
    }

    private function guardMember(): void
    {
        abort_unless($this->members->contains('id', $this->memberId), 403);
    }

    private function detachExistingMealAttendance(string $date, string $slot): void
    {
        $hh = auth()->user()->household_id;
        MealPlan::where('household_id', $hh)
            ->whereDate('date', $date)
            ->where('slot', $slot)
            ->each(fn ($plan) => $plan->attendees()->detach($this->memberId));
    }

    public function render()
    {
        $today = CarbonImmutable::today(auth()->user()->getTimezone())->toDateString();

        return view('livewire.availability', compact('today'));
    }
}
