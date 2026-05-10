<?php

namespace App\Livewire;

use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use Livewire\Component;

class FamilyTree extends Component
{
    protected const PARENT_TYPES = ['father', 'mother', 'step-father', 'step-mother'];

    protected const PARTNER_TYPES = ['husband', 'wife', 'boyfriend', 'girlfriend', 'fiance', 'fiancee'];

    protected const GUEST_ALLOWED_TYPES = ['boyfriend', 'girlfriend', 'fiance', 'fiancee'];

    public ?int $focusMemberId = null;

    public function render()
    {
        $householdId = auth()->user()->household_id;

        $allMembers = FamilyMember::where('household_id', $householdId)
            ->with('user')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $allIds = $allMembers->keys()->all();

        $allConnections = FamilyConnection::whereIn('from_member_id', $allIds)
            ->whereIn('to_member_id', $allIds)
            ->get();

        // Guests only appear if linked to a non-guest via boyfriend/girlfriend/fiancé(e).
        $allowedGuestIds = [];
        foreach ($allConnections as $c) {
            if (! in_array($c->type, self::GUEST_ALLOWED_TYPES, true)) {
                continue;
            }
            $from = $allMembers[$c->from_member_id] ?? null;
            $to = $allMembers[$c->to_member_id] ?? null;
            if (! $from || ! $to) {
                continue;
            }
            if ($from->is_guest && ! $to->is_guest) {
                $allowedGuestIds[$from->id] = true;
            }
            if ($to->is_guest && ! $from->is_guest) {
                $allowedGuestIds[$to->id] = true;
            }
        }

        $members = $allMembers->filter(fn ($m) => ! $m->is_guest || isset($allowedGuestIds[$m->id]));

        $memberIds = $members->keys()->all();

        $connections = $allConnections->filter(
            fn ($c) => in_array($c->from_member_id, $memberIds, true) && in_array($c->to_member_id, $memberIds, true)
        );

        // Build parent/child maps from parental connection types only.
        $parentsOf = [];
        $childrenOf = [];
        foreach ($connections as $c) {
            if (in_array($c->type, self::PARENT_TYPES, true)) {
                $parentsOf[$c->to_member_id][] = $c->from_member_id;
                $childrenOf[$c->from_member_id][] = $c->to_member_id;
            }
        }

        // Partner pairs (undirected, deduped).
        $partnerPairs = [];
        foreach ($connections as $c) {
            if (! in_array($c->type, self::PARTNER_TYPES, true)) {
                continue;
            }
            $key = $c->from_member_id < $c->to_member_id
                ? $c->from_member_id.'-'.$c->to_member_id
                : $c->to_member_id.'-'.$c->from_member_id;
            $partnerPairs[$key] = [$c->from_member_id, $c->to_member_id];
        }

        // Assign generation levels: roots (no parents) at 0, children +1 from max parent.
        $generation = [];
        foreach ($members as $m) {
            if (empty($parentsOf[$m->id] ?? [])) {
                $generation[$m->id] = 0;
            }
        }

        $changed = true;
        $guard = 0;
        while ($changed && $guard++ < 50) {
            $changed = false;
            foreach ($members as $m) {
                $parentIds = $parentsOf[$m->id] ?? [];
                if (empty($parentIds)) {
                    continue;
                }
                $parentGens = [];
                foreach ($parentIds as $pid) {
                    if (isset($generation[$pid])) {
                        $parentGens[] = $generation[$pid];
                    }
                }
                if (empty($parentGens)) {
                    continue;
                }
                $newGen = max($parentGens) + 1;
                if (! isset($generation[$m->id]) || $generation[$m->id] !== $newGen) {
                    $generation[$m->id] = $newGen;
                    $changed = true;
                }
            }
        }

        // Pull partners into the same generation as their partner where possible.
        foreach ($partnerPairs as [$a, $b]) {
            $ga = $generation[$a] ?? null;
            $gb = $generation[$b] ?? null;
            if ($ga !== null && $gb === null) {
                $generation[$b] = $ga;
            } elseif ($gb !== null && $ga === null) {
                $generation[$a] = $gb;
            }
        }

        // Anything still ungenerated (orphan or cycle) goes to row 0.
        foreach ($members as $m) {
            if (! isset($generation[$m->id])) {
                $generation[$m->id] = 0;
            }
        }

        // Identify immediate family of the current user: self, partners, parents, children.
        $selfId = $members->firstWhere('user_id', auth()->id())?->id;
        $immediateIds = [];
        if ($selfId !== null) {
            $immediateIds[$selfId] = true;
            foreach ($parentsOf[$selfId] ?? [] as $pid) {
                $immediateIds[$pid] = true;
            }
            foreach ($childrenOf[$selfId] ?? [] as $cid) {
                $immediateIds[$cid] = true;
            }
            foreach ($partnerPairs as [$a, $b]) {
                if ($a === $selfId) {
                    $immediateIds[$b] = true;
                } elseif ($b === $selfId) {
                    $immediateIds[$a] = true;
                }
            }
        }

        // Map each guest to the non-guest household member they're connected to.
        $guestsOf = [];
        $guestHostId = [];
        foreach ($partnerPairs as [$a, $b]) {
            $aMember = $members[$a] ?? null;
            $bMember = $members[$b] ?? null;
            if (! $aMember || ! $bMember) {
                continue;
            }
            if ($aMember->is_guest && ! $bMember->is_guest) {
                $guestsOf[$b][] = $aMember;
                $guestHostId[$a] = $b;
            } elseif ($bMember->is_guest && ! $aMember->is_guest) {
                $guestsOf[$a][] = $bMember;
                $guestHostId[$b] = $a;
            }
        }

        // Group by generation, sort each row by name for stability. Guests render
        // attached to their host, not as standalone columns.
        $rows = [];
        foreach ($members as $m) {
            if ($m->is_guest && isset($guestHostId[$m->id])) {
                continue;
            }
            $rows[$generation[$m->id]][] = $m;
        }
        ksort($rows);
        foreach ($rows as &$row) {
            usort($row, fn ($a, $b) => strcmp($a->name, $b->name));

            if ($this->focusMemberId === null) {
                continue;
            }

            $focusIndex = null;
            foreach ($row as $i => $m) {
                if ($m->id === $this->focusMemberId) {
                    $focusIndex = $i;
                    break;
                }
            }

            if ($focusIndex === null) {
                continue;
            }

            $focused = $row[$focusIndex];
            $others = array_values(array_filter($row, fn ($m) => $m->id !== $this->focusMemberId));
            $half = intdiv(count($others), 2);
            $row = array_merge(array_slice($others, 0, $half), [$focused], array_slice($others, $half));
        }
        unset($row);

        return view('livewire.family-tree', [
            'members' => $members,
            'rows' => $rows,
            'parentsOf' => $parentsOf,
            'childrenOf' => $childrenOf,
            'immediateIds' => $immediateIds,
            'guestsOf' => $guestsOf,
        ]);
    }
}
