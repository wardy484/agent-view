<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Snapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * REQ-M4-004: sent (on-demand, via notifyNow/routeNotification) to an email
 * that does NOT correspond to an existing user. Links to the registration
 * form; {@see CreateNewUser} backfills the snapshot share
 * row's `user_id` on signup.
 */
class SnapshotInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Snapshot $snapshot) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to view a snapshot')
            ->line('You have been invited to view a Nexus-UI snapshot.')
            ->line('Create an account with this email address to gain access.')
            ->action('Create account', route('register'))
            ->line('Once you sign up, the snapshot will appear under "Shared with me" in your sidebar.');
    }
}
