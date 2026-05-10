<?php

namespace App\Livewire;

use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use Livewire\Component;

class FamilyConnections extends Component
{
    public ?int $fromId = null;

    public string $type = 'father';

    public ?int $toId = null;

    public string $notes = '';

    public ?int $focusMemberId = null;

    public string $view = 'tree';

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['list', 'tree'], true) ? $view : 'list';
    }

    public ?int $reciprocalFromId = null;

    public ?int $reciprocalToId = null;

    public ?string $reciprocalType = null;

    /** @var array<int, string> */
    public array $reciprocalOptions = [];

    public function rules(): array
    {
        return [
            'fromId' => ['required', 'integer', 'different:toId'],
            'toId' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(FamilyConnection::TYPES))],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function add(): void
    {
        $this->validate();

        $householdId = auth()->user()->household_id;
        $valid = FamilyMember::where('household_id', $householdId)
            ->whereIn('id', [$this->fromId, $this->toId])
            ->count() === 2;

        abort_unless($valid, 403);

        FamilyConnection::firstOrCreate([
            'from_member_id' => $this->fromId,
            'to_member_id' => $this->toId,
            'type' => $this->type,
        ], ['notes' => $this->notes ?: null]);

        $this->suggestReciprocal($this->fromId, $this->toId, $this->type);

        $this->reset(['type', 'notes', 'toId']);
        $this->type = 'father';

        $this->modal('connection-form')->close();
    }

    protected function suggestReciprocal(int $fromId, int $toId, string $type): void
    {
        $options = FamilyConnection::RECIPROCALS[$type] ?? [];
        if (empty($options)) {
            $this->clearReciprocal();

            return;
        }

        $alreadyExists = FamilyConnection::where('from_member_id', $toId)
            ->where('to_member_id', $fromId)
            ->whereIn('type', $options)
            ->exists();

        if ($alreadyExists) {
            $this->clearReciprocal();

            return;
        }

        $this->reciprocalFromId = $toId;
        $this->reciprocalToId = $fromId;
        $this->reciprocalOptions = $options;
        $this->reciprocalType = $options[0];
    }

    public function confirmReciprocal(): void
    {
        if (! $this->reciprocalFromId || ! $this->reciprocalToId || ! $this->reciprocalType) {
            return;
        }

        if (! in_array($this->reciprocalType, $this->reciprocalOptions, true)) {
            return;
        }

        $householdId = auth()->user()->household_id;
        $valid = FamilyMember::where('household_id', $householdId)
            ->whereIn('id', [$this->reciprocalFromId, $this->reciprocalToId])
            ->count() === 2;

        abort_unless($valid, 403);

        FamilyConnection::firstOrCreate([
            'from_member_id' => $this->reciprocalFromId,
            'to_member_id' => $this->reciprocalToId,
            'type' => $this->reciprocalType,
        ]);

        $this->clearReciprocal();
    }

    public function dismissReciprocal(): void
    {
        $this->clearReciprocal();
    }

    protected function clearReciprocal(): void
    {
        $this->reciprocalFromId = null;
        $this->reciprocalToId = null;
        $this->reciprocalType = null;
        $this->reciprocalOptions = [];
    }

    public function remove(int $id): void
    {
        $householdId = auth()->user()->household_id;

        $connection = FamilyConnection::with('fromMember', 'toMember')->find($id);
        if (! $connection || $connection->fromMember?->household_id !== $householdId) {
            return;
        }

        $connection->delete();
    }

    public function focus(?int $memberId): void
    {
        $this->focusMemberId = $memberId;
    }

    public function render()
    {
        $householdId = auth()->user()->household_id;

        $members = FamilyMember::where('household_id', $householdId)
            ->with('user')
            ->orderBy('is_guest')
            ->orderBy('name')
            ->get();

        $connectionsQuery = FamilyConnection::with('fromMember.user', 'toMember.user')
            ->whereHas('fromMember', fn ($q) => $q->where('household_id', $householdId));

        if ($this->focusMemberId) {
            $connectionsQuery->where(function ($q) {
                $q->where('from_member_id', $this->focusMemberId)
                    ->orWhere('to_member_id', $this->focusMemberId);
            });
        }

        $connections = $connectionsQuery->get();

        if ($this->fromId === null && $members->isNotEmpty()) {
            $this->fromId = $members->first()->id;
        }

        if ($this->toId === null && $members->count() >= 2) {
            $this->toId = $members->firstWhere(fn ($m) => $m->id !== $this->fromId)?->id;
        }

        return view('livewire.family-connections', [
            'members' => $members,
            'pairs' => $connections,
            'types' => FamilyConnection::TYPES,
        ]);
    }
}
