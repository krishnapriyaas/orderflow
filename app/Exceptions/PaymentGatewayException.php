<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by PaymentGateway implementations when the provider cannot create a payment.
 */
class PaymentGatewayException extends RuntimeException {}
