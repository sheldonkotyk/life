<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Models\BookingCalendarSelection;
use App\Models\CalendarSyncState;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps a live push channel on every destination calendar. Channels expire
 * after days, so this renews them well before they lapse; the poll remains the
 * safety net for anything a notification misses.
 */
class WatchCalendars
{
    private const TTL_SECONDS = 604800;

    public function __construct(private GoogleCalendar $googleCalendar) {}

    /**
     * @return array{watched: int, renewed: int, failed: int}
     */
    public function execute(): array
    {
        $address = config('services.google.calendar_webhook_url') ?: route('google-calendar.webhook');

        // Google refuses anything but https, and never localhost.
        if (! str_starts_with($address, 'https://')) {
            return ['watched' => 0, 'renewed' => 0, 'failed' => 0];
        }

        $watched = 0;
        $renewed = 0;
        $failed = 0;

        foreach ($this->destinations() as $destination) {
            $state = CalendarSyncState::firstOrCreate([
                'google_calendar_connection_id' => $destination->google_calendar_connection_id,
                'google_calendar_id' => $destination->google_calendar_id,
            ]);

            if (! $state->needsWatchRenewal($address)) {
                continue;
            }

            $replacing = $state->channel_id !== null;

            try {
                $this->renew($destination, $state, $address);
                $watched++;
                $renewed += $replacing ? 1 : 0;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return ['watched' => $watched, 'renewed' => $renewed, 'failed' => $failed];
    }

    private function renew(BookingCalendarSelection $destination, CalendarSyncState $state, string $address): void
    {
        // Stop the old channel first so Google is not pushing twice — but only
        // ours: a channel belonging to another environment is left to expire.
        if ($state->channel_id && $state->channel_resource_id && $state->channelBelongsTo($address)) {
            try {
                $this->googleCalendar->stopWatch(
                    $destination->connection,
                    $state->channel_id,
                    $state->channel_resource_id,
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $channelId = (string) Str::uuid();
        $token = Str::random(40);

        $channel = $this->googleCalendar->watchEvents(
            $destination->connection,
            $destination->google_calendar_id,
            $channelId,
            $address,
            $token,
            self::TTL_SECONDS,
        );

        $state->update([
            'channel_id' => $channelId,
            'channel_resource_id' => $channel['resource_id'],
            'channel_token' => $token,
            'channel_address' => $address,
            'channel_expires_at' => $channel['expires_at'],
        ]);
    }

    /**
     * @return Collection<int, BookingCalendarSelection>
     */
    private function destinations()
    {
        return BookingCalendarSelection::query()
            ->where('receives_bookings', true)
            ->with('connection.oauthToken')
            ->get()
            ->filter(fn (BookingCalendarSelection $selection): bool => $selection->connection !== null)
            ->unique(fn (BookingCalendarSelection $selection): string => $selection->google_calendar_connection_id.'|'.$selection->google_calendar_id);
    }
}
