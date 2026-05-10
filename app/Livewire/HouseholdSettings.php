<?php

namespace App\Livewire;

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class HouseholdSettings extends Component
{
    public ?int $householdId = null;

    public string $name = '';

    public string $inviteCode = '';

    public bool $choosingSuccessor = false;

    public ?int $successorId = null;

    #[Url(as: 'tab', history: true, keep: false)]
    public string $tab = 'people';

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

    private function finalizeLeave(User $user, Household $household): void
    {
        $household->users()->detach($user->id);
        $this->moveToFallbackHousehold($user);

        session()->flash('status', 'You left '.$household->name.'.');
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

        session()->flash('status', 'Deleted '.$name.'.');
        $this->redirectRoute('household', navigate: true);
    }

    private function moveToFallbackHousehold(User $user): void
    {
        $next = $user->households()->orderBy('households.id')->first();

        if (! $next) {
            $next = Household::create(['name' => ($user->name ?: 'Your')."'s Household"]);
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
