<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentWebhookEventType: string
{
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';

    /**
     * The order status this event moves the order to.
     */
    public function targetStatus(): OrderStatus
    {
        return match ($this) {
            self::PaymentSucceeded => OrderStatus::Paid,
            self::PaymentFailed => OrderStatus::PaymentFailed,
        };
    }
}
