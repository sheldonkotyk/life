<?php

namespace App\Livewire;

use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use Livewire\Component;

class FamilyTree extends Component
{
    protected const PARENT_TYPES = ['father', 'mother', 'step-father', 'step-mother'];

    protected const PARTNER_TYPES = ['husband', 'wife', 'boyfriend', 'girlfriend'];

    public function render()
    {
        $householdId = auth()->user()->household_id;

        $members = FamilyMember::where('household_id', $householdId)
            ->where('is_guest', false)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $memberIds = $members->keys()->all();

        $connections = FamilyConnection::whereIn('from_member_id', $memberIds)
            ->whereIn('to_member_id', $memberIds)
            ->get();

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

        // Group by generation, sort each row by name for stability.
        $rows = [];
        foreach ($members as $m) {
            $rows[$generation[$m->id]][] = $m;
        }
        ksort($rows);
        foreach ($rows as &$row) {
            usort($row, fn ($a, $b) => strcmp($a->name, $b->name));
        }
        unset($row);

        return view('livewire.family-tree', [
            'members' => $members,
            'rows' => $rows,
            'parentsOf' => $parentsOf,
            'childrenOf' => $childrenOf,
            'partnerPairs' => array_values($partnerPairs),
        ]);
    }
}
