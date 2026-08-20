<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use App\Services\FamilyTreeBuilder;
use App\Support\ApiPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyConnectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $householdId = $request->user()->household_id;

        $connections = FamilyConnection::whereHas(
            'fromMember',
            fn ($query) => $query->where('household_id', $householdId)
        )->get();

        return response()->json([
            'types' => collect(FamilyConnection::TYPES)
                ->map(fn (array $type) => $type['label'])
                ->all(),
            'reciprocals' => FamilyConnection::RECIPROCALS,
            'connections' => $connections->map(fn (FamilyConnection $connection) => [
                'id' => $connection->id,
                'from_member_id' => $connection->from_member_id,
                'to_member_id' => $connection->to_member_id,
                'type' => $connection->type,
                'label' => $connection->typeLabel(),
                'notes' => $connection->notes,
            ])->values()->all(),
        ]);
    }

    /**
     * Record a relationship, and offer the mirror image of it.
     *
     * "Father of" almost always implies "son of" coming back, but which one
     * depends on the child, so the reverse link is suggested rather than made.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_member_id' => ['required', 'integer', 'different:to_member_id'],
            'to_member_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(FamilyConnection::TYPES))],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->authorizeMembers($request, [$data['from_member_id'], $data['to_member_id']]);

        $connection = FamilyConnection::firstOrCreate([
            'from_member_id' => $data['from_member_id'],
            'to_member_id' => $data['to_member_id'],
            'type' => $data['type'],
        ], ['notes' => $data['notes'] ?? null]);

        return response()->json([
            'connection' => [
                'id' => $connection->id,
                'from_member_id' => $connection->from_member_id,
                'to_member_id' => $connection->to_member_id,
                'type' => $connection->type,
                'label' => $connection->typeLabel(),
                'notes' => $connection->notes,
            ],
            'suggested_reciprocal' => $this->suggestReciprocal(
                $data['from_member_id'],
                $data['to_member_id'],
                $data['type'],
            ),
        ], 201);
    }

    public function destroy(Request $request, FamilyConnection $connection): JsonResponse
    {
        $connection->load('fromMember');
        abort_unless($connection->fromMember?->household_id === $request->user()->household_id, 403);

        $connection->delete();

        return response()->json(['ok' => true]);
    }

    public function tree(Request $request): JsonResponse
    {
        $focus = $request->integer('focus_member_id') ?: null;

        $tree = app(FamilyTreeBuilder::class)->build(
            $request->user()->household_id,
            $request->user()->id,
            $focus,
        );

        return response()->json([
            'focus_member_id' => $focus,
            'members' => $tree['members']->map(fn (FamilyMember $member) => ApiPayload::member($member))->values(),
            'rows' => array_values(array_map(
                fn (array $row) => array_map(fn (FamilyMember $member) => $member->id, $row),
                $tree['rows'],
            )),
            'parents_of' => (object) $tree['parentsOf'],
            'children_of' => (object) $tree['childrenOf'],
            'partner_pairs' => array_values($tree['partnerPairs']),
            'immediate_ids' => array_keys($tree['immediateIds']),
            'guests_of' => (object) array_map(
                fn (array $guests) => array_map(fn (FamilyMember $guest) => $guest->id, $guests),
                $tree['guestsOf'],
            ),
        ]);
    }

    /**
     * @return array{from_member_id: int, to_member_id: int, options: list<string>, type: string}|null
     */
    private function suggestReciprocal(int $fromId, int $toId, string $type): ?array
    {
        $options = FamilyConnection::RECIPROCALS[$type] ?? [];

        if (empty($options)) {
            return null;
        }

        $exists = FamilyConnection::where('from_member_id', $toId)
            ->where('to_member_id', $fromId)
            ->whereIn('type', $options)
            ->exists();

        if ($exists) {
            return null;
        }

        return [
            'from_member_id' => $toId,
            'to_member_id' => $fromId,
            'options' => array_values($options),
            'type' => $options[0],
        ];
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function authorizeMembers(Request $request, array $memberIds): void
    {
        $count = FamilyMember::where('household_id', $request->user()->household_id)
            ->whereIn('id', $memberIds)
            ->count();

        abort_unless($count === count(array_unique($memberIds)), 403);
    }
}
