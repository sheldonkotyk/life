<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class HouseholdSettings extends Component
{
    public const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public const SLOTS = ['breakfast', 'lunch', 'dinner'];

    public ?int $householdId = null;

    public string $name = '';

    public string $inviteCode = '';

    public string $joinCode = '';

    public bool $choosingSuccessor = false;

    public ?int $successorId = null;

    public ?int $attendanceMemberId = null;

    public function mount(): void
    {
        $household = auth()->user()->household;

        abort_unless($household, 404);

        $this->householdId = $household->id;
        $this->name = $household->name;
        $this->inviteCode = $household->invite_code;

        $this->attendanceMemberId = $this->defaultAttendanceMemberId();
    }

    private function defaultAttendanceMemberId(): ?int
    {
        $available = $this->availableAttendanceMembers();

        $own = $available->firstWhere('user_id', auth()->id());

        return $own?->id ?? $available->first()?->id;
    }

    private function availableAttendanceMembers()
    {
        $query = $this->household()->members()->orderBy('is_guest')->orderBy('name');

        if ($this->canManage()) {
            return $query->get();
        }

        return $query->where('user_id', auth()->id())->get();
    }

    public function selectedAttendanceMember(): ?FamilyMember
    {
        if (! $this->attendanceMemberId) {
            return null;
        }

        return $this->availableAttendanceMembers()
            ->firstWhere('id', $this->attendanceMemberId);
    }

    public function toggleAttendance(string $day, string $slot): void
    {
        if (! in_array($day, self::DAYS, true) || ! in_array($slot, self::SLOTS, true)) {
            return;
        }

        $member = $this->selectedAttendanceMember();
        abort_unless($member, 404);

        $member->setDefaultAttendance($day, $slot, ! $member->attendsByDefault($day, $slot));
    }

    public function setDayAttendance(string $day, bool $value): void
    {
        if (! in_array($day, self::DAYS, true)) {
            return;
        }

        $member = $this->selectedAttendanceMember();
        abort_unless($member, 404);

        foreach (self::SLOTS as $slot) {
            $member->setDefaultAttendance($day, $slot, $value);
        }
    }

    public function setSlotAttendance(string $slot, bool $value): void
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return;
        }

        $member = $this->selectedAttendanceMember();
        abort_unless($member, 404);

        foreach (self::DAYS as $day) {
            $member->setDefaultAttendance($day, $slot, $value);
        }
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

        session()->flash('status', 'You joined '.$household->name.'.');
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
            'attendanceMembers' => $this->availableAttendanceMembers(),
            'attendanceMember' => $this->selectedAttendanceMember(),
        ]);
    }
}
