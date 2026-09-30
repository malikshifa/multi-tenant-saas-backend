<?php

namespace App\Notifications\Tenant;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately NOT queued: see the note on TrialExpiringSoon — a ShouldQueue
 * notification to a Tenant\User loses tenant DB context on a worker. This is
 * always sent from inside a tenant-authenticated request, which already has
 * the right tenant connected.
 */
class UserInvited extends Notification
{
    public function __construct(
        public readonly string $acceptUrl
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited')
            ->line('You have been invited to join a team.')
            ->action('Accept invitation', $this->acceptUrl)
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
