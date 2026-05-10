<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyTodayDigest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-daily-digest')]
#[Description("Send the daily 'today' email to users whose preferred local time has arrived.")]
class SendDailyTodayDigests extends Command
{
    /**
     * Granularity of the cron schedule, in minutes. Users get the email at the
     * scheduled tick that falls within this window after their chosen time.
     */
    public const WINDOW_MINUTES = 5;

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');
        $sent = 0;

        User::query()
            ->whereNotNull('daily_today_email_at')
            ->whereNotNull('email')
            ->chunkById(200, function ($users) use ($now, &$sent) {
                foreach ($users as $user) {
                    if ($this->shouldSend($user, $now)) {
                        $user->notify(new DailyTodayDigest);
                        $user->forceFill([
                            'daily_today_email_last_sent_on' => CarbonImmutable::now($user->getTimezone())->toDateString(),
                        ])->saveQuietly();
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} daily digest email(s).");

        return self::SUCCESS;
    }

    protected function shouldSend(User $user, CarbonImmutable $nowUtc): bool
    {
        if (! $user->wantsNotificationOn('email')) {
            return false;
        }

        $tz = $user->getTimezone();
        $localNow = $nowUtc->setTimezone($tz);
        $localToday = $localNow->toDateString();

        if ((string) $user->daily_today_email_last_sent_on === $localToday) {
            return false;
        }

        $preferred = CarbonImmutable::parse(
            $localToday.' '.$user->daily_today_email_at,
            $tz,
        );

        if ($localNow->lt($preferred)) {
            return false;
        }

        return $localNow->diffInMinutes($preferred) < self::WINDOW_MINUTES;
    }
}
