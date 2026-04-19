<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesEmailTemplate;

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     */
    public function __construct(
        public Collection $notifications,
        public string $frequency,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = $this->notifications->map(function ($n) {
            $title = $n->data['title'] ?? class_basename($n->type);
            $message = $n->data['message'] ?? '';

            return '• '.$title.($message !== '' ? ' — '.$message : '');
        })->implode("\n");

        return $this->renderTemplate('notification-digest', $notifiable, [
            'count' => (string) $this->notifications->count(),
            'frequency' => __('notifications.digest.frequency_'.$this->frequency),
            'lines' => $lines,
            'app_name' => (string) config('app.name'),
            'app_url' => (string) config('app.url'),
        ]);
    }
}
