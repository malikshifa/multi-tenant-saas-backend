<?php

namespace App\Notifications\Platform;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment failed')
            ->line("Payment #{$this->payment->id} failed.")
            ->line("Reason: {$this->payment->failure_reason}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'tenant_id' => $this->payment->tenant_id,
            'reason' => $this->payment->failure_reason,
            'message' => "Payment #{$this->payment->id} failed.",
        ];
    }
}
