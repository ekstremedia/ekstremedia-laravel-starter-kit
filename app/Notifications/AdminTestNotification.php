<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminTestNotification extends Notification
{
    use Queueable;

    public function __construct(public string $message = 'Hello from the admin panel!') {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test notification')
            ->greeting("Hi {$notifiable->first_name},")
            ->line($this->message)
            ->line('This is a test notification sent from the admin dashboard.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Test notification',
            'message' => $this->message,
            'icon' => 'pi-bell',
        ];
    }
}
