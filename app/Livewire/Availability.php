<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
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
        unset($this->unavailableKeys);
    }

    public function jumpToToday(): void
    {
        $this->weekStart = CarbonImmutable::today(auth()->user()->getTimezone())->toDateString();
        unset($this->unavailableKeys);
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
        return FamilyMember::where('household_id', auth()->user()->household_id)
            ->orderBy('name')->get();
    }

    #[Computed]
    public function unavailableKeys(): array
    {
        if (! $this->memberId) return [];

        $weekStart = CarbonImmutable::parse($this->weekStart);
        $start = $weekStart->toDateString();
        $end = $weekStart->addDays(6)->toDateString();

        return FamilyMemberUnavailability::where('family_member_id', $this->memberId)
            ->whereBetween('date', [$start, $end])
            ->get()
            ->map(fn ($u) => $u->date->toDateString() . '|' . $u->slot)
            ->all();
    }

    public function setAttending(string $date, string $slot, bool $attend): void
    {
        if (! in_array($slot, self::SLOTS, true)) return;
        $this->guardMember();

        if ($attend) {
            FamilyMemberUnavailability::where('family_member_id', $this->memberId)
                ->whereDate('date', $date)->where('slot', $slot)->delete();
        } else {
            FamilyMemberUnavailability::firstOrCreate([
                'family_member_id' => $this->memberId,
                'date' => $date,
                'slot' => $slot,
            ]);
            $this->detachExistingMealAttendance($date, $slot);
        }

        unset($this->unavailableKeys);
    }

    public function setSlotAttending(string $slot, bool $attend): void
    {
        if (! in_array($slot, self::SLOTS, true)) return;
        $this->guardMember();

        $dates = array_map(fn ($d) => $d->toDateString(), $this->days());

        if ($attend) {
            FamilyMemberUnavailability::where('family_member_id', $this->memberId)
                ->where('slot', $slot)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('DATE(date)'), $dates)
                ->delete();
        } else {
            foreach ($dates as $date) {
                FamilyMemberUnavailability::firstOrCreate([
                    'family_member_id' => $this->memberId,
                    'date' => $date,
                    'slot' => $slot,
                ]);
                $this->detachExistingMealAttendance($date, $slot);
            }
        }

        unset($this->unavailableKeys);
    }

    public function setDayAttending(string $date, bool $attend): void
    {
        $this->guardMember();

        if ($attend) {
            FamilyMemberUnavailability::where('family_member_id', $this->memberId)
                ->whereDate('date', $date)->whereIn('slot', self::SLOTS)->delete();
        } else {
            foreach (self::SLOTS as $slot) {
                FamilyMemberUnavailability::firstOrCreate([
                    'family_member_id' => $this->memberId,
                    'date' => $date,
                    'slot' => $slot,
                ]);
                $this->detachExistingMealAttendance($date, $slot);
            }
        }

        unset($this->unavailableKeys);
    }

    private function guardMember(): void
    {
        $member = FamilyMember::where('household_id', auth()->user()->household_id)
            ->findOrFail($this->memberId);
        $this->memberId = $member->id;
    }

    private function detachExistingMealAttendance(string $date, string $slot): void
    {
        $hh = auth()->user()->household_id;
        \App\Models\MealPlan::where('household_id', $hh)
            ->whereDate('date', $date)
            ->where('slot', $slot)
            ->each(fn ($plan) => $plan->attendees()->detach($this->memberId));
    }

    public function render()
    {
        return view('livewire.availability');
    }
}
