<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\PayPalGateway;
use App\Services\Payments\Gateways\StripeGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function make(
        PaymentMethod|string $method
    ): PaymentGatewayInterface {

        $method = $method instanceof PaymentMethod
            ? $method
            : PaymentMethod::tryFrom($method);

        if (! $method) {
            throw new InvalidArgumentException(
                'Unsupported payment method.'
            );
        }

        return match ($method) {

            PaymentMethod::STRIPE => app(StripeGateway::class),

            PaymentMethod::PAYPAL => app(PayPalGateway::class),
        };
    }
}
