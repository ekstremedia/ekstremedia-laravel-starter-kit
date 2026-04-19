<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;

class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:digest {--frequency=daily : Frequency to send (daily or weekly)}';

    protected $description = 'Send notification digest emails to users who opted in';

    public function handle(): int
    {
        $frequency = $this->option('frequency');

        if (! in_array($frequency, ['daily', 'weekly'])) {
            $this->error("Invalid frequency: {$frequency}. Use 'daily' or 'weekly'.");

            return self::FAILURE;
        }

        $users = User::notBanned()
            ->whereHas('setting', function ($q) use ($frequency) {
                $q->whereJsonContains('settings->notification_digest', $frequency);
            })
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            $unread = $user->unreadNotifications()
                ->where('created_at', '>=', $frequency === 'daily' ? now()->subDay() : now()->subWeek())
                ->get();

            if ($unread->isEmpty()) {
                continue;
            }

            Mail::raw(
                $this->buildDigestBody($user, $unread),
                function ($mail) use ($user) {
                    $mail->to($user->email)
                        ->subject(__('Your notification digest'));
                }
            );

            $sent++;
        }

        $this->info("Sent {$sent} digest email(s) for frequency '{$frequency}'.");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DatabaseNotification>  $notifications
     */
    private function buildDigestBody(User $user, $notifications): string
    {
        $lines = ["Hi {$user->first_name},\n"];
        $lines[] = "Here's a summary of your {$notifications->count()} unread notification(s):\n";

        foreach ($notifications as $n) {
            $title = $n->data['title'] ?? class_basename($n->type);
            $message = $n->data['message'] ?? '';
            $lines[] = "• {$title}" . ($message ? " — {$message}" : '');
        }

        $lines[] = "\n— ".config('app.name');

        return implode("\n", $lines);
    }
}
