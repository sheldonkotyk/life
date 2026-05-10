<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyConnection extends Model
{
    protected $guarded = [];

    public const TYPES = [
        'father' => ['label' => 'Father of'],
        'mother' => ['label' => 'Mother of'],
        'step-father' => ['label' => 'Step-father of'],
        'step-mother' => ['label' => 'Step-mother of'],
        'son' => ['label' => 'Son of'],
        'daughter' => ['label' => 'Daughter of'],
        'step-son' => ['label' => 'Step-son of'],
        'step-daughter' => ['label' => 'Step-daughter of'],
        'husband' => ['label' => 'Husband of'],
        'wife' => ['label' => 'Wife of'],
        'boyfriend' => ['label' => 'Boyfriend of'],
        'girlfriend' => ['label' => 'Girlfriend of'],
        'fiance' => ['label' => 'Fiancé of'],
        'fiancee' => ['label' => 'Fiancée of'],
        'brother' => ['label' => 'Brother of'],
        'sister' => ['label' => 'Sister of'],
        'grandfather' => ['label' => 'Grandfather of'],
        'grandmother' => ['label' => 'Grandmother of'],
        'grandson' => ['label' => 'Grandson of'],
        'granddaughter' => ['label' => 'Granddaughter of'],
        'friend' => ['label' => 'Friend of'],
        'other' => ['label' => 'Connected to'],
    ];

    public const RECIPROCALS = [
        'father' => ['son', 'daughter'],
        'mother' => ['son', 'daughter'],
        'step-father' => ['step-son', 'step-daughter'],
        'step-mother' => ['step-son', 'step-daughter'],
        'son' => ['father', 'mother'],
        'daughter' => ['father', 'mother'],
        'step-son' => ['step-father', 'step-mother'],
        'step-daughter' => ['step-father', 'step-mother'],
        'husband' => ['wife', 'husband'],
        'wife' => ['husband', 'wife'],
        'boyfriend' => ['girlfriend', 'boyfriend'],
        'girlfriend' => ['boyfriend', 'girlfriend'],
        'fiance' => ['fiancee', 'fiance'],
        'fiancee' => ['fiance', 'fiancee'],
        'brother' => ['brother', 'sister'],
        'sister' => ['brother', 'sister'],
        'grandfather' => ['grandson', 'granddaughter'],
        'grandmother' => ['grandson', 'granddaughter'],
        'grandson' => ['grandfather', 'grandmother'],
        'granddaughter' => ['grandfather', 'grandmother'],
        'friend' => ['friend'],
    ];

    public function fromMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'from_member_id');
    }

    public function toMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'to_member_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? ucfirst($this->type);
    }
}
