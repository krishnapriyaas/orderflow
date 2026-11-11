<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\PaymentIntent;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;

interface PaymentGateway
{
    /**
     * Starts a payment for the order at the provider.
     *
     * @throws PaymentGatewayException when the provider cannot create the payment
     */
    public function createPayment(Order $order): PaymentIntent;
}
