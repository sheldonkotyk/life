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
    public bool $choosingSuccessor = false;
    public ?int $successorId = null;

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

    public function leaveHousehold(): void
    {
        $user = auth()->user();
        $household = $this->household();

        if ($household->users()->count() <= 1) {
            return;
        }

        $isSoleAdmin = $household->admins()->count() === 1
            && $household->admins()->where('users.id', $user->id)->exists();
        $otherMembers = $household->users()->where('users.id', '!=', $user->id);

        if ($isSoleAdmin) {
            if ($otherMembers->count() === 1) {
                $household->users()->updateExistingPivot($otherMembers->first()->id, ['role' => 'admin']);
            } else {
                $this->choosingSuccessor = true;
                return;
            }
        }

        $this->finalizeLeave($user, $household);
    }

    public function confirmLeaveWithSuccessor(): void
    {
        $user = auth()->user();
        $household = $this->household();

        $this->validate([
            'successorId' => ['required', 'integer'],
        ]);

        abort_unless(
            $household->users()->where('users.id', $this->successorId)->where('users.id', '!=', $user->id)->exists(),
            422
        );

        $household->users()->updateExistingPivot($this->successorId, ['role' => 'admin']);
        $this->choosingSuccessor = false;
        $this->finalizeLeave($user, $household);
    }

    public function cancelLeave(): void
    {
        $this->choosingSuccessor = false;
        $this->successorId = null;
    }

    private function finalizeLeave(\App\Models\User $user, Household $household): void
    {
        $household->users()->detach($user->id);
        $this->moveToFallbackHousehold($user);

        session()->flash('status', 'You left ' . $household->name . '.');
        $this->redirectRoute('household', navigate: true);
    }

    public function leaveAndDeleteHousehold(): void
    {
        $user = auth()->user();
        $household = $this->household();

        if ($household->users()->where('users.id', '!=', $user->id)->exists()) {
            return;
        }

        $household->users()->detach($user->id);
        $name = $household->name;
        $household->delete();

        $this->moveToFallbackHousehold($user);

        session()->flash('status', 'Deleted ' . $name . '.');
        $this->redirectRoute('household', navigate: true);
    }

    private function moveToFallbackHousehold(\App\Models\User $user): void
    {
        $next = $user->households()->orderBy('households.id')->first();

        if (! $next) {
            $next = Household::create(['name' => ($user->name ?: 'Your') . "'s Household"]);
            $user->joinHousehold($next);
            return;
        }

        $user->forceFill(['household_id' => $next->id])->save();
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
