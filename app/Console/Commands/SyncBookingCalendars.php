<?php

namespace App\Console\Commands;

use App\Actions\SyncCalendarChanges;
use Illuminate\Console\Command;

class SyncBookingCalendars extends Command
{
    protected $signature = 'bookings:sync-calendars';

    protected $description = 'Reconcile bookings with declines, moves, and deletions made in Google Calendar';

    public function handle(SyncCalendarChanges $syncCalendarChanges): int
    {
        $result = $syncCalendarChanges->execute();

        $this->info(sprintf(
            'Synced %d calendar(s): %d booking(s) updated, %d calendar(s) failed.',
            $result['calendars'],
            $result['changed'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
