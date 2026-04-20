<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Snapshot;
use App\Policies\SnapshotPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * REQ-M4-004: sent to an existing user when they are added as a viewer on a
 * `visibility=shared` snapshot. The user can sign in and hit the snapshot URL
 * directly — {@see SnapshotPolicy::view()} grants access via
 * the matching `snapshot_shares` row.
 */
class SnapshotSharedNotification extends Notification implements ShouldQueue
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
        $url = route('workbench.snapshot.show', [
            'workbench' => $this->snapshot->workbench->slug,
            'snapshot' => $this->snapshot->slug,
        ]);

        return (new MailMessage)
            ->subject('A snapshot has been shared with you')
            ->line('You have been granted view access to a Nexus-UI snapshot.')
            ->action('View snapshot', $url)
            ->line('Sign in with your existing account to see the latest revision.');
    }
}
