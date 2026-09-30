<?php

namespace App\Notifications\Tenant;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately NOT queued: Laravel's SendQueuedNotifications job doesn't
 * forward a notification's own middleware() to the queue, so a ShouldQueue
 * notification sent to a Tenant\User would lose tenant DB context on a
 * worker (the same problem TenantAware solves for jobs, unsolved for
 * notifications). This is always dispatched from code that already has the
 * right tenant connected, so sending it inline is both simpler and correct.
 */
class TrialExpiringSoon extends Notification
{
    public function __construct(
        protected Subscription $subscription
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->subscription->trial_ends_at->toFormattedDateString();

        return (new MailMessage)
            ->subject('Your trial is ending soon')
            ->line("Your trial ends on {$date}. Add a payment method to keep your subscription active.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'trial_ends_at' => $this->subscription->trial_ends_at,
            'message' => 'Your trial is ending soon.',
        ];
    }
}
