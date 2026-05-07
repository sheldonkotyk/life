<?php

namespace App\Livewire;

use App\Models\Household;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class HouseholdSettings extends Component
{
    public ?int $householdId = null;
    public string $name = '';
    public string $inviteCode = '';
    public string $joinCode = '';

    public function mount(): void
    {
        $household = auth()->user()->household;

        abort_unless($household, 404);

        $this->householdId = $household->id;
        $this->name = $household->name;
        $this->inviteCode = $household->invite_code;
    }

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()->canManageHousehold($this->household());
    }

    public function save(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $this->household()->update($data);
        session()->flash('status', 'Household updated.');
    }

    public function regenerateInviteCode(): void
    {
        $this->authorizeManage();

        $code = strtoupper(Str::random(8));
        $this->household()->update(['invite_code' => $code]);
        $this->inviteCode = $code;
        session()->flash('status', 'Invite code regenerated.');
    }

    public function joinHousehold(): void
    {
        $this->validate([
            'joinCode' => ['required', 'string', 'max:12'],
        ]);

        $code = strtoupper(trim($this->joinCode));
        $household = Household::where('invite_code', $code)->first();

        if (! $household) {
            $this->addError('joinCode', 'No household found for that code.');
            return;
        }

        $user = auth()->user();

        if ($user->households()->where('households.id', $household->id)->exists()
            && $user->household_id === $household->id) {
            $this->addError('joinCode', 'You are already in that household.');
            return;
        }

        $user->joinHousehold($household);

        $this->joinCode = '';
        $this->householdId = $household->id;
        $this->name = $household->name;
        $this->inviteCode = $household->invite_code;

        session()->flash('status', 'You joined ' . $household->name . '.');
        $this->redirectRoute('household', navigate: true);
    }

    public function makeAdmin(int $userId): void
    {
        $this->authorizeManage();

        $household = $this->household();
        abort_unless($household->users()->where('users.id', $userId)->exists(), 404);

        $household->users()->updateExistingPivot($userId, ['role' => 'admin']);
        session()->flash('status', 'Administrator added.');
    }

    public function removeAdmin(int $userId): void
    {
        $this->authorizeManage();

        $household = $this->household();
        abort_unless($household->users()->where('users.id', $userId)->exists(), 404);

        if ($household->admins()->count() <= 1 && $household->admins()->where('users.id', $userId)->exists()) {
            session()->flash('status', 'A household must have at least one administrator.');
            return;
        }

        $household->users()->updateExistingPivot($userId, ['role' => null]);
        session()->flash('status', 'Administrator removed.');
    }

    private function household(): Household
    {
        $household = auth()->user()->household;
        abort_unless($household && $household->id === $this->householdId, 403);

        return $household;
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->canManageHousehold($this->household()), 403);
    }

    public function render()
    {
        return view('livewire.household-settings', [
            'members' => $this->household()->users()->orderBy('name')->get(),
        ]);
    }
}
