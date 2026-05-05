<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMemberUnavailability extends Model
{
    protected $table = 'family_member_unavailabilities';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }
}
