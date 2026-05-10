<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\TodayDigest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyTodayDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(User $notifiable): array
    {
        $channels = [];

        if ($notifiable->wantsNotificationOn('site')) {
            $channels[] = 'database';
        }

        if ($notifiable->wantsNotificationOn('email') && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(User $notifiable): array
    {
        $digest = TodayDigest::for($notifiable);
        $dateLabel = $digest['date']->format('l, F j');
        $mealCount = count($digest['meals']);
        $todoCount = count($digest['todos']);

        $body = match (true) {
            $mealCount === 0 && $todoCount === 0 => 'Nothing on the schedule today.',
            default => trim(
                ($mealCount > 0 ? "{$mealCount} meal".($mealCount === 1 ? '' : 's') : '').
                ($mealCount > 0 && $todoCount > 0 ? ' · ' : '').
                ($todoCount > 0 ? "{$todoCount} to-do".($todoCount === 1 ? '' : 's') : '')
            ),
        };

        return [
            'title' => "Your day — {$dateLabel}",
            'body' => $body,
            'url' => '/today',
            'icon' => 'sun',
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $digest = TodayDigest::for($notifiable);
        $dateLabel = $digest['date']->format('l, F j');
        $household = $notifiable->household;
        $brand = $household?->name ?: config('app.name');

        return (new MailMessage)
            ->subject("{$brand} — {$dateLabel}")
            ->view('emails.daily-today', [
                'user' => $notifiable,
                'digest' => $digest,
                'dateLabel' => $dateLabel,
                'todayUrl' => url('/today'),
                'brand' => $brand,
            ]);
    }
}
