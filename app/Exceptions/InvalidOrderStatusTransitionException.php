<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;
use DomainException;

class InvalidOrderStatusTransitionException extends DomainException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct(sprintf(
            'Order status cannot change from %s to %s.',
            $from->value,
            $to->value,
        ));
    }
}
