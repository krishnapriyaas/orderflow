<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\PaymentIntent;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;

/**
 * Implementations must catch every provider error (HTTP errors, timeouts, SDK
 * exceptions, invalid responses) and rethrow it as a PaymentGatewayException,
 * keeping the original as `previous`. CheckoutCart only handles
 * PaymentGatewayException: it marks the order payment_failed and returns 502.
 * Any other exception becomes a 500 and leaves the order in pending_payment.
 */
interface PaymentGateway
{
    /**
     * Starts a payment for the order at the provider.
     *
     * @throws PaymentGatewayException when the provider cannot create the payment
     */
    public function createPayment(Order $order): PaymentIntent;
}
