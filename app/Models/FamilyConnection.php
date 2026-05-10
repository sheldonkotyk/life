<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyConnection extends Model
{
    protected $guarded = [];

    public const TYPES = [
        'parent' => ['label' => 'Parent of', 'reciprocal' => 'child'],
        'child' => ['label' => 'Child of', 'reciprocal' => 'parent'],
        'son' => ['label' => 'Son of', 'reciprocal' => 'parent'],
        'daughter' => ['label' => 'Daughter of', 'reciprocal' => 'parent'],
        'spouse' => ['label' => 'Spouse of', 'reciprocal' => 'spouse'],
        'husband' => ['label' => 'Husband of', 'reciprocal' => 'spouse'],
        'wife' => ['label' => 'Wife of', 'reciprocal' => 'spouse'],
        'partner' => ['label' => 'Partner of', 'reciprocal' => 'partner'],
        'boyfriend' => ['label' => 'Boyfriend of', 'reciprocal' => 'partner'],
        'girlfriend' => ['label' => 'Girlfriend of', 'reciprocal' => 'partner'],
        'sibling' => ['label' => 'Sibling of', 'reciprocal' => 'sibling'],
        'grandparent' => ['label' => 'Grandparent of', 'reciprocal' => 'grandchild'],
        'grandchild' => ['label' => 'Grandchild of', 'reciprocal' => 'grandparent'],
        'friend' => ['label' => 'Friend of', 'reciprocal' => 'friend'],
        'other' => ['label' => 'Connected to', 'reciprocal' => 'other'],
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

    public static function reciprocalType(string $type): string
    {
        return self::TYPES[$type]['reciprocal'] ?? $type;
    }
}
