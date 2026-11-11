<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Order;

final readonly class CheckoutResult
{
    /**
     * @param  bool  $replayed  true when an earlier order was returned for the same Idempotency-Key
     */
    public function __construct(
        public Order $order,
        public bool $replayed,
    ) {}
}
