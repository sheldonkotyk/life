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
        'channel_id',
        'channel_resource_id',
        'channel_token',
        'channel_address',
        'channel_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'immutable_datetime',
            'channel_expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * A channel is worth renewing once it is inside its last few hours, so a
     * lapse never leaves a calendar unwatched between runs.
     */
    public function needsWatchRenewal(string $address): bool
    {
        return $this->channel_id === null
            || $this->channel_expires_at === null
            || $this->channel_address !== $address
            || $this->channel_expires_at->isBefore(now()->addHours(6));
    }

    /**
     * A channel opened by another environment — a database copied from
     * production keeps production's — must be left for Google to expire rather
     * than closed from here.
     */
    public function channelBelongsTo(string $address): bool
    {
        return $this->channel_address === $address;
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class, 'google_calendar_connection_id');
    }
}
