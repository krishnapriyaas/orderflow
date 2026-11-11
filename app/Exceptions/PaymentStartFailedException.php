<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class PaymentStartFailedException extends RuntimeException
{
    public function __construct(public readonly Order $order, ?Throwable $previous = null)
    {
        parent::__construct('The payment could not be started. Please try again later.', 0, $previous);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'order' => [
                'uuid' => $this->order->uuid,
                'status' => $this->order->status->value,
            ],
        ], 502);
    }
}
