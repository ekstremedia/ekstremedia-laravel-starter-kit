<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use App\Notifications\Concerns\UsesEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification implements ShouldBroadcast
{
    use Queueable;
    use UsesEmailTemplate;

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

        // Chat messages intentionally skip the `database` channel so they
        // don't clutter the notification inbox / bell. The dedicated message
        // icon is the sole in-app indicator; the broadcast channel only
        // exists so that indicator can update live.
        $channels = ['broadcast'];

        if ($settings['notification_email_immediate'] ?? false) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->renderTemplate('new-chat-message', $notifiable, [
            'sender_name' => $this->sender->fullName(),
            'message_preview' => $this->messagePreview(),
            'app_name' => config('app.name'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->sender->fullName(),
            'message' => $this->messagePreview(),
            'icon' => 'pi-comments',
            'conversation_id' => $this->message->conversation_id,
        ];
    }

    private function messagePreview(): string
    {
        return mb_strlen($this->message->body) > 100
            ? mb_substr($this->message->body, 0, 100).'…'
            : $this->message->body;
    }
}
