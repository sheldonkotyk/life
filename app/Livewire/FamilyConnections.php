<?php

namespace App\Livewire;

use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use Livewire\Component;

class FamilyConnections extends Component
{
    public ?int $fromId = null;

    public string $type = 'parent';

    public ?int $toId = null;

    public string $notes = '';

    public ?int $focusMemberId = null;

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

        $reciprocal = FamilyConnection::reciprocalType($this->type);
        FamilyConnection::firstOrCreate([
            'from_member_id' => $this->toId,
            'to_member_id' => $this->fromId,
            'type' => $reciprocal,
        ]);

        $this->reset(['type', 'notes', 'toId']);
        $this->type = 'parent';
    }

    public function remove(int $id): void
    {
        $householdId = auth()->user()->household_id;

        $connection = FamilyConnection::with('fromMember', 'toMember')->find($id);
        if (! $connection || $connection->fromMember?->household_id !== $householdId) {
            return;
        }

        FamilyConnection::where(function ($q) use ($connection) {
            $q->where(['from_member_id' => $connection->from_member_id, 'to_member_id' => $connection->to_member_id])
                ->orWhere(function ($q2) use ($connection) {
                    $q2->where('from_member_id', $connection->to_member_id)
                        ->where('to_member_id', $connection->from_member_id);
                });
        })->delete();
    }

    public function focus(?int $memberId): void
    {
        $this->focusMemberId = $memberId;
    }

    public function render()
    {
        $householdId = auth()->user()->household_id;

        $members = FamilyMember::where('household_id', $householdId)
            ->orderBy('is_guest')
            ->orderBy('name')
            ->get();

        $connectionsQuery = FamilyConnection::with('fromMember', 'toMember')
            ->whereHas('fromMember', fn ($q) => $q->where('household_id', $householdId));

        if ($this->focusMemberId) {
            $connectionsQuery->where(function ($q) {
                $q->where('from_member_id', $this->focusMemberId)
                    ->orWhere('to_member_id', $this->focusMemberId);
            });
        }

        $connections = $connectionsQuery->get();

        // Collapse reciprocal pairs — show the one whose from_member_id is lower
        // so each relationship appears once in the list.
        $pairs = [];
        $seen = [];
        foreach ($connections as $c) {
            $key = min($c->from_member_id, $c->to_member_id).':'.max($c->from_member_id, $c->to_member_id);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $pairs[] = $c->from_member_id <= $c->to_member_id
                ? $c
                : $connections->firstWhere(fn ($x) => $x->from_member_id === $c->to_member_id && $x->to_member_id === $c->from_member_id) ?? $c;
        }

        if ($this->fromId === null && $members->isNotEmpty()) {
            $this->fromId = $members->first()->id;
        }

        if ($this->toId === null && $members->count() >= 2) {
            $this->toId = $members->firstWhere(fn ($m) => $m->id !== $this->fromId)?->id;
        }

        return view('livewire.family-connections', [
            'members' => $members,
            'pairs' => collect($pairs),
            'types' => FamilyConnection::TYPES,
        ]);
    }
}
