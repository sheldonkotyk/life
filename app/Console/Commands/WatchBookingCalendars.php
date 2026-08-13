<?php

namespace App\Console\Commands;

use App\Actions\WatchCalendars;
use Illuminate\Console\Command;

class WatchBookingCalendars extends Command
{
    protected $signature = 'bookings:watch-calendars';

    protected $description = 'Subscribe to Google push notifications for every calendar that receives bookings';

    public function handle(WatchCalendars $watchCalendars): int
    {
        $result = $watchCalendars->execute();

        $this->info(sprintf(
            '%d channel(s) opened (%d renewals), %d failed.',
            $result['watched'],
            $result['renewed'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
