<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;

class InvoiceService
{
    public function createFromPayment(Payment $payment): Invoice
    {
        return Invoice::create([
            'tenant_id' => $payment->tenant_id,
            'subscription_id' => $payment->subscription_id,
            'payment_id' => $payment->id,
            'invoice_number' => 'INV-'.str_pad($payment->id, 6, '0', STR_PAD_LEFT),
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'issued_at' => now(),
        ]);
    }
}
