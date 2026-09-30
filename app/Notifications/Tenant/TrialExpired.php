<?php

namespace App\Notifications\Tenant;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately NOT queued: see the note on TrialExpiringSoon — a ShouldQueue
 * notification to a Tenant\User loses tenant DB context on a worker.
 */
class TrialExpired extends Notification
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
        return (new MailMessage)
            ->subject('Your trial has ended')
            ->line('Your trial has ended. Add a payment method to reactivate your subscription.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => 'Your trial has ended.',
        ];
    }
}
