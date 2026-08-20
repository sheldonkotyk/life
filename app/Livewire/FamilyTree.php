<?php

namespace App\Livewire;

use App\Services\FamilyTreeBuilder;
use Livewire\Component;

class FamilyTree extends Component
{
    public ?int $focusMemberId = null;

    public function render()
    {
        $tree = app(FamilyTreeBuilder::class)->build(
            auth()->user()->household_id,
            auth()->id(),
            $this->focusMemberId,
        );

        return view('livewire.family-tree', [
            'members' => $tree['members'],
            'rows' => $tree['rows'],
            'parentsOf' => $tree['parentsOf'],
            'childrenOf' => $tree['childrenOf'],
            'immediateIds' => $tree['immediateIds'],
            'guestsOf' => $tree['guestsOf'],
        ]);
    }
}
