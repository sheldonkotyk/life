<?php

namespace App\Livewire;

use App\Models\Household;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class HouseholdMealTimes extends Component
{
    public ?int $householdId = null;

    public string $breakfastStart = '07:00';

    public string $breakfastEnd = '09:00';

    public string $lunchStart = '11:30';

    public string $lunchEnd = '13:30';

    public string $dinnerStart = '17:30';

    public string $dinnerEnd = '19:30';

    public function mount(): void
    {
        $household = auth()->user()->household;

        abort_unless($household, 404);

        $this->householdId = $household->id;
        $this->loadMealTimes($household);
    }

    private function loadMealTimes(Household $household): void
    {
        $this->breakfastStart = self::formatTime($household->breakfast_start_time);
        $this->breakfastEnd = self::formatTime($household->breakfast_end_time);
        $this->lunchStart = self::formatTime($household->lunch_start_time);
        $this->lunchEnd = self::formatTime($household->lunch_end_time);
        $this->dinnerStart = self::formatTime($household->dinner_start_time);
        $this->dinnerEnd = self::formatTime($household->dinner_end_time);
    }

    public static function formatTime(?string $value): string
    {
        return $value ? substr($value, 0, 5) : '';
    }

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()->canManageHousehold($this->household());
    }

    public function save(): void
    {
        abort_unless(auth()->user()->canManageHousehold($this->household()), 403);

        $rules = ['date_format:H:i'];
        $this->validate([
            'breakfastStart' => $rules,
            'breakfastEnd' => [...$rules, 'after:breakfastStart'],
            'lunchStart' => $rules,
            'lunchEnd' => [...$rules, 'after:lunchStart'],
            'dinnerStart' => $rules,
            'dinnerEnd' => [...$rules, 'after:dinnerStart'],
        ]);

        $this->household()->update([
            'breakfast_start_time' => $this->breakfastStart,
            'breakfast_end_time' => $this->breakfastEnd,
            'lunch_start_time' => $this->lunchStart,
            'lunch_end_time' => $this->lunchEnd,
            'dinner_start_time' => $this->dinnerStart,
            'dinner_end_time' => $this->dinnerEnd,
        ]);

        $this->loadMealTimes($this->household()->fresh());
        session()->flash('status', 'Default meal times updated.');
    }

    private function household(): Household
    {
        $household = auth()->user()->household;
        abort_unless($household && $household->id === $this->householdId, 403);

        return $household;
    }

    public function render()
    {
        return view('livewire.household-meal-times');
    }
}
