<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Message $message,
        public User $sender,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $settings = $notifiable->settings()->resolved();

        if (! ($settings['notification_chat_messages'] ?? true)) {
            return [];
        }

        $channels = ['database'];

        if ($settings['notification_email_immediate'] ?? false) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $body = mb_strlen($this->message->body) > 100
            ? mb_substr($this->message->body, 0, 100).'…'
            : $this->message->body;

        return [
            'title' => $this->sender->fullName(),
            'message' => $body,
            'icon' => 'pi-comments',
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
