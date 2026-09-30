<?php

namespace App\Notifications\Platform;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRefunded extends Notification implements ShouldQueue
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
            ->subject('Payment refunded')
            ->line("Payment #{$this->payment->id} for {$this->payment->amount} {$this->payment->currency} was refunded.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'tenant_id' => $this->payment->tenant_id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'message' => "Payment #{$this->payment->id} for {$this->payment->amount} {$this->payment->currency} was refunded.",
        ];
    }
}
