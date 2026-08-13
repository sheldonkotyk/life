<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarSyncState extends Model
{
    protected $fillable = [
        'google_calendar_connection_id',
        'google_calendar_id',
        'sync_token',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'immutable_datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class, 'google_calendar_connection_id');
    }
}
