<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PaymentRequest;
use App\Http\Resources\Platform\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index()
    {
        return PaymentResource::collection(
            Payment::with(['tenant', 'subscription.plan'])->latest()->paginate(20)
        );
    }

    public function store(PaymentRequest $request)
    {

        $result = $this->paymentService->createCheckout($request->validated());

        return response()->json([
            'message' => 'Hosted checkout created successfully.',
            'data' => [
                'payment' => $result['payment'],
                'checkout_url' => $result['checkout_url'],
            ],
        ], 201);
    }

    public function show(Payment $payment)
    {

        return response()->json([
            'data' => PaymentResource::make($payment->load(['tenant', 'subscription.plan'])),
        ]);
    }

    public function verify(Payment $payment)
    {

        $payment = $this->paymentService->verify($payment);

        return response()->json([
            'message' => 'Payment verified successfully.',
            'data' => PaymentResource::make($payment),
        ]);
    }

    public function refund(Payment $payment)
    {

        $payment = $this->paymentService->refund($payment);

        return response()->json([
            'message' => 'Payment refunded successfully.',
            'data' => PaymentResource::make($payment),
        ]);
    }
}
