<?php

namespace App\Services;

use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use Illuminate\Support\Collection;

/**
 * Arrange a household's members into generations from their stated relationships.
 *
 * Nobody records "generation" anywhere — it is inferred from parent links, so
 * this is the only place that decides what a row of the tree means. The web
 * view and the mobile API both read it from here rather than each guessing.
 */
class FamilyTreeBuilder
{
    public const PARENT_TYPES = ['father', 'mother', 'step-father', 'step-mother'];

    public const PARTNER_TYPES = ['husband', 'wife', 'boyfriend', 'girlfriend', 'fiance', 'fiancee'];

    /** Guests are outsiders; only a romantic link to a member earns them a place. */
    public const GUEST_ALLOWED_TYPES = ['boyfriend', 'girlfriend', 'fiance', 'fiancee'];

    /**
     * @return array{
     *     members: Collection<int, FamilyMember>,
     *     rows: array<int, list<FamilyMember>>,
     *     parentsOf: array<int, list<int>>,
     *     childrenOf: array<int, list<int>>,
     *     partnerPairs: array<string, array{0: int, 1: int}>,
     *     immediateIds: array<int, true>,
     *     guestsOf: array<int, list<FamilyMember>>,
     *     generation: array<int, int>,
     * }
     */
    public function build(int $householdId, ?int $selfUserId = null, ?int $focusMemberId = null): array
    {
        $allMembers = FamilyMember::where('household_id', $householdId)
            ->with('user')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $allIds = $allMembers->keys()->all();

        $allConnections = FamilyConnection::whereIn('from_member_id', $allIds)
            ->whereIn('to_member_id', $allIds)
            ->get();

        $allowedGuestIds = $this->allowedGuestIds($allMembers, $allConnections);

        $members = $allMembers->filter(
            fn (FamilyMember $member) => ! $member->is_guest || isset($allowedGuestIds[$member->id])
        );

        $memberIds = $members->keys()->all();

        $connections = $allConnections->filter(
            fn (FamilyConnection $c) => in_array($c->from_member_id, $memberIds, true)
                && in_array($c->to_member_id, $memberIds, true)
        );

        [$parentsOf, $childrenOf] = $this->parentMaps($connections);
        $partnerPairs = $this->partnerPairs($connections);
        $generation = $this->generations($members, $parentsOf, $partnerPairs);
        [$guestsOf, $guestHostId] = $this->guestHosts($members, $partnerPairs);

        return [
            'members' => $members,
            'rows' => $this->rows($members, $generation, $guestHostId, $focusMemberId),
            'parentsOf' => $parentsOf,
            'childrenOf' => $childrenOf,
            'partnerPairs' => $partnerPairs,
            'immediateIds' => $this->immediateIds($members, $parentsOf, $childrenOf, $partnerPairs, $selfUserId),
            'guestsOf' => $guestsOf,
            'generation' => $generation,
        ];
    }

    /**
     * @param  Collection<int, FamilyMember>  $allMembers
     * @param  Collection<int, FamilyConnection>  $allConnections
     * @return array<int, true>
     */
    private function allowedGuestIds(Collection $allMembers, Collection $allConnections): array
    {
        $allowed = [];

        foreach ($allConnections as $connection) {
            if (! in_array($connection->type, self::GUEST_ALLOWED_TYPES, true)) {
                continue;
            }

            $from = $allMembers[$connection->from_member_id] ?? null;
            $to = $allMembers[$connection->to_member_id] ?? null;

            if (! $from || ! $to) {
                continue;
            }

            if ($from->is_guest && ! $to->is_guest) {
                $allowed[$from->id] = true;
            }

            if ($to->is_guest && ! $from->is_guest) {
                $allowed[$to->id] = true;
            }
        }

        return $allowed;
    }

    /**
     * @param  Collection<int, FamilyConnection>  $connections
     * @return array{0: array<int, list<int>>, 1: array<int, list<int>>}
     */
    private function parentMaps(Collection $connections): array
    {
        $parentsOf = [];
        $childrenOf = [];

        foreach ($connections as $connection) {
            if (! in_array($connection->type, self::PARENT_TYPES, true)) {
                continue;
            }

            $parentsOf[$connection->to_member_id][] = $connection->from_member_id;
            $childrenOf[$connection->from_member_id][] = $connection->to_member_id;
        }

        return [$parentsOf, $childrenOf];
    }

    /**
     * @param  Collection<int, FamilyConnection>  $connections
     * @return array<string, array{0: int, 1: int}>
     */
    private function partnerPairs(Collection $connections): array
    {
        $pairs = [];

        foreach ($connections as $connection) {
            if (! in_array($connection->type, self::PARTNER_TYPES, true)) {
                continue;
            }

            $key = $connection->from_member_id < $connection->to_member_id
                ? $connection->from_member_id.'-'.$connection->to_member_id
                : $connection->to_member_id.'-'.$connection->from_member_id;

            $pairs[$key] = [$connection->from_member_id, $connection->to_member_id];
        }

        return $pairs;
    }

    /**
     * Roots sit at 0 and every child lands one below its lowest parent. Cycles
     * are possible in hand-entered data, so the sweep is capped rather than
     * trusted to settle.
     *
     * @param  Collection<int, FamilyMember>  $members
     * @param  array<int, list<int>>  $parentsOf
     * @param  array<string, array{0: int, 1: int}>  $partnerPairs
     * @return array<int, int>
     */
    private function generations(Collection $members, array $parentsOf, array $partnerPairs): array
    {
        $generation = [];

        foreach ($members as $member) {
            if (empty($parentsOf[$member->id] ?? [])) {
                $generation[$member->id] = 0;
            }
        }

        $changed = true;
        $guard = 0;

        while ($changed && $guard++ < 50) {
            $changed = false;

            foreach ($members as $member) {
                $parentIds = $parentsOf[$member->id] ?? [];

                if (empty($parentIds)) {
                    continue;
                }

                $parentGenerations = [];
                foreach ($parentIds as $parentId) {
                    if (isset($generation[$parentId])) {
                        $parentGenerations[] = $generation[$parentId];
                    }
                }

                if (empty($parentGenerations)) {
                    continue;
                }

                $next = max($parentGenerations) + 1;

                if (! isset($generation[$member->id]) || $generation[$member->id] !== $next) {
                    $generation[$member->id] = $next;
                    $changed = true;
                }
            }
        }

        foreach ($partnerPairs as [$a, $b]) {
            $genA = $generation[$a] ?? null;
            $genB = $generation[$b] ?? null;

            if ($genA !== null && $genB === null) {
                $generation[$b] = $genA;
            } elseif ($genB !== null && $genA === null) {
                $generation[$a] = $genB;
            }
        }

        foreach ($members as $member) {
            if (! isset($generation[$member->id])) {
                $generation[$member->id] = 0;
            }
        }

        return $generation;
    }

    /**
     * @param  Collection<int, FamilyMember>  $members
     * @param  array<string, array{0: int, 1: int}>  $partnerPairs
     * @return array{0: array<int, list<FamilyMember>>, 1: array<int, int>}
     */
    private function guestHosts(Collection $members, array $partnerPairs): array
    {
        $guestsOf = [];
        $guestHostId = [];

        foreach ($partnerPairs as [$a, $b]) {
            $memberA = $members[$a] ?? null;
            $memberB = $members[$b] ?? null;

            if (! $memberA || ! $memberB) {
                continue;
            }

            if ($memberA->is_guest && ! $memberB->is_guest) {
                $guestsOf[$b][] = $memberA;
                $guestHostId[$a] = $b;
            } elseif ($memberB->is_guest && ! $memberA->is_guest) {
                $guestsOf[$a][] = $memberB;
                $guestHostId[$b] = $a;
            }
        }

        return [$guestsOf, $guestHostId];
    }

    /**
     * @param  Collection<int, FamilyMember>  $members
     * @param  array<int, int>  $generation
     * @param  array<int, int>  $guestHostId
     * @return array<int, list<FamilyMember>>
     */
    private function rows(Collection $members, array $generation, array $guestHostId, ?int $focusMemberId): array
    {
        $rows = [];

        foreach ($members as $member) {
            if ($member->is_guest && isset($guestHostId[$member->id])) {
                continue;
            }

            $rows[$generation[$member->id]][] = $member;
        }

        ksort($rows);

        foreach ($rows as &$row) {
            usort($row, fn (FamilyMember $a, FamilyMember $b) => strcmp($a->name, $b->name));

            if ($focusMemberId === null) {
                continue;
            }

            $focusIndex = null;
            foreach ($row as $index => $member) {
                if ($member->id === $focusMemberId) {
                    $focusIndex = $index;
                    break;
                }
            }

            if ($focusIndex === null) {
                continue;
            }

            // The person in focus is pulled to the middle of their row so the
            // lines to their relatives fan out either side instead of crossing.
            $focused = $row[$focusIndex];
            $others = array_values(array_filter($row, fn (FamilyMember $m) => $m->id !== $focusMemberId));
            $half = intdiv(count($others), 2);
            $row = array_merge(array_slice($others, 0, $half), [$focused], array_slice($others, $half));
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  Collection<int, FamilyMember>  $members
     * @param  array<int, list<int>>  $parentsOf
     * @param  array<int, list<int>>  $childrenOf
     * @param  array<string, array{0: int, 1: int}>  $partnerPairs
     * @return array<int, true>
     */
    private function immediateIds(
        Collection $members,
        array $parentsOf,
        array $childrenOf,
        array $partnerPairs,
        ?int $selfUserId,
    ): array {
        $selfId = $selfUserId ? $members->firstWhere('user_id', $selfUserId)?->id : null;

        if ($selfId === null) {
            return [];
        }

        $immediate = [$selfId => true];

        foreach ($parentsOf[$selfId] ?? [] as $parentId) {
            $immediate[$parentId] = true;
        }

        foreach ($childrenOf[$selfId] ?? [] as $childId) {
            $immediate[$childId] = true;
        }

        foreach ($partnerPairs as [$a, $b]) {
            if ($a === $selfId) {
                $immediate[$b] = true;
            } elseif ($b === $selfId) {
                $immediate[$a] = true;
            }
        }

        return $immediate;
    }
}
