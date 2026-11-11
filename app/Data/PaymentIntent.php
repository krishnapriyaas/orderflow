<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PaymentIntentStatus;

final readonly class PaymentIntent
{
    public function __construct(
        public string $reference,
        public PaymentIntentStatus $status,
        public string $redirectUrl,
    ) {}
}
