<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountBannedNotification extends Notification
{
    use Queueable;

    public function __construct(public ?string $reason = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your account has been suspended')
            ->greeting("Hi {$notifiable->first_name},")
            ->line('Your account has been suspended by an administrator.');

        if ($this->reason) {
            $mail->line("Reason: {$this->reason}");
        }

        return $mail->line('Contact support if you believe this is a mistake.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account suspended',
            'message' => $this->reason ?? 'Your account has been suspended by an administrator.',
            'icon' => 'pi-ban',
        ];
    }
}
