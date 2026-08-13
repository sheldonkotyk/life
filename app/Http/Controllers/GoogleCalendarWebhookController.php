<?php

namespace App\Http\Controllers;

use App\Actions\SyncCalendarChanges;
use App\Models\CalendarSyncState;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Google's push notifications. The request carries no body and no credentials
 * beyond the token we chose when subscribing, so it is treated as nothing more
 * than a nudge to go and read the calendar ourselves.
 */
class GoogleCalendarWebhookController extends Controller
{
    public function __invoke(Request $request, SyncCalendarChanges $syncCalendarChanges): Response
    {
        $channelId = (string) $request->header('X-Goog-Channel-ID');
        $state = $channelId === '' ? null : CalendarSyncState::firstWhere('channel_id', $channelId);

        // An unknown channel, or one whose secret does not match, is answered
        // the same way: acknowledged and ignored.
        if (! $state || ! hash_equals((string) $state->channel_token, (string) $request->header('X-Goog-Channel-Token'))) {
            return response()->noContent();
        }

        // The first message after subscribing only confirms the channel exists.
        if ($request->header('X-Goog-Resource-State') === 'sync') {
            return response()->noContent();
        }

        $syncCalendarChanges->syncOne($state);

        return response()->noContent();
    }
}
