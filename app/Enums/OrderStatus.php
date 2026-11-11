<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidOrderStatusTransitionException;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case PaymentFailed = 'payment_failed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::PaymentFailed, self::Cancelled],
            // payment_failed → paid: a late payment.succeeded webhook wins. The provider is
            // the source of truth for money, and the stock is still reserved.
            self::PaymentFailed => [self::Paid, self::PendingPayment, self::Cancelled],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Returns the target status, or throws if the move is not allowed.
     *
     * @throws InvalidOrderStatusTransitionException
     */
    public function transitionTo(self $to): self
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidOrderStatusTransitionException($this, $to);
        }

        return $to;
    }
}
