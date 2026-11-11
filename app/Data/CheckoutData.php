<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CheckoutData
{
    public function __construct(
        public string $email,
        public ?string $note,
        public ?string $idempotencyKey,
    ) {}
}
