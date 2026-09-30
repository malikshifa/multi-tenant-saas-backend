<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Platform\PaymentCompleted;
use App\Notifications\Platform\PaymentFailed;
use App\Notifications\Platform\PaymentRefunded;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
        protected InvoiceService $invoices
    ) {}

    public function createCheckout(array $data): array
    {
        $subscription = Subscription::with('plan')
            ->findOrFail($data['subscription_id']);

        if ($subscription->status === SubscriptionStatus::CANCELLED) {
            throw new RuntimeException('Cannot pay for a cancelled subscription.');
        }

        // The amount always comes from the plan, never from the request.
        $payment = Payment::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'amount' => $subscription->plan->price,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'payment_method' => $data['payment_method'],
            'status' => PaymentStatus::PENDING,
        ]);

        try {
            $result = $this->gatewayFactory
                ->make($payment->payment_method)
                ->createCheckout($payment->load('subscription.plan'));

            $payment->update([
                'gateway_reference' => $result['gateway_reference'],
                'checkout_url' => $result['checkout_url'],
            ]);

            return [
                'payment' => $payment->fresh(),
                'checkout_url' => $result['checkout_url'],
            ];
        } catch (\Throwable $e) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            $this->notifyAdmins('payments.view', new PaymentFailed($payment->fresh()));

            throw $e;
        }
    }

    public function verify(Payment $payment): Payment
    {
        if (! $payment->gateway_reference) {
            throw new RuntimeException('Payment gateway reference is missing.');
        }

        $result = $this->gatewayFactory
            ->make($payment->payment_method)
            ->verify($payment);

        if ($this->isPaid($payment->payment_method, $result['status'] ?? null)) {
            $this->markCompleted($payment, $result['transaction_id'] ?? null);
        }

        return $payment->fresh();
    }

    public function markCompleted(Payment $payment, ?string $transactionId = null): Payment
    {
        $wasAlreadyCompleted = false;

        $completed = DB::transaction(function () use ($payment, $transactionId, &$wasAlreadyCompleted) {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::COMPLETED) {
                $wasAlreadyCompleted = true;

                return $locked;
            }

            $locked->update([
                'status' => PaymentStatus::COMPLETED,
                'transaction_id' => $transactionId,
                'paid_at' => now(),
                'failure_reason' => null,
            ]);

            $this->invoices->createFromPayment($locked);

            return $locked->fresh();
        });

        if (! $wasAlreadyCompleted) {
            // Dispatched after the transaction commits, so a queued listener
            // can never pick this up before the row is actually visible.
            $this->notifyAdmins('payments.view', new PaymentCompleted($completed));
        }

        return $completed;
    }

    public function refund(Payment $payment): Payment
    {
        $refunded = DB::transaction(function () use ($payment) {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== PaymentStatus::COMPLETED) {
                throw new RuntimeException('Only completed payments can be refunded.');
            }

            $this->gatewayFactory
                ->make($locked->payment_method)
                ->refund($locked);

            $locked->update(['status' => PaymentStatus::REFUNDED]);

            return $locked->fresh();
        });

        $this->notifyAdmins('payments.view', new PaymentRefunded($refunded));

        return $refunded;
    }

    protected function isPaid(PaymentMethod $method, ?string $gatewayStatus): bool
    {
        return match ($method) {
            PaymentMethod::STRIPE => $gatewayStatus === 'paid',
            PaymentMethod::PAYPAL => $gatewayStatus === 'COMPLETED',
        };
    }

    protected function notifyAdmins(string $permission, $notification): void
    {
        // Notifying admins is a side effect of the payment succeeding, failing
        // or refunding, not a precondition for it: a missing permission (e.g.
        // seeders never ran) must never stop the payment itself from recording.
        try {
            Notification::send(User::permission($permission)->get(), $notification);
        } catch (PermissionDoesNotExist) {
            // Nothing to notify yet.
        }
    }
}
