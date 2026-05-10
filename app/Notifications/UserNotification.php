<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{title: string, body?: string|null, url?: string|null, icon?: string|null, action_label?: string|null, channels?: array<int, string>}  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->payload['channels'] ?? ['database', 'mail'];

        if (in_array('mail', $channels, true) && empty($notifiable->email ?? null)) {
            $channels = array_values(array_diff($channels, ['mail']));
        }

        $prefMap = ['database' => 'site', 'mail' => 'email', 'push' => 'push'];

        return array_values(array_filter($channels, function (string $channel) use ($notifiable, $prefMap) {
            $pref = $prefMap[$channel] ?? null;

            if ($pref === null || ! method_exists($notifiable, 'wantsNotificationOn')) {
                return true;
            }

            return $notifiable->wantsNotificationOn($pref);
        }));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->payload['title']);

        if (! empty($this->payload['body'])) {
            $mail->line($this->payload['body']);
        }

        if (! empty($this->payload['url'])) {
            $mail->action($this->payload['action_label'] ?? 'View', $this->payload['url']);
        }

        return $mail;
    }

    /**
     * @return array{title: string, body: string|null, url: string|null, icon: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->payload['title'],
            'body' => $this->payload['body'] ?? null,
            'url' => $this->payload['url'] ?? null,
            'icon' => $this->payload['icon'] ?? 'bell',
        ];
    }
}
