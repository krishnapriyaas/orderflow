<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PaymentWebhookEvent
{
    /**
     * @param  string  $type  raw provider type; may be a type we do not handle
     * @param  array<string, mixed>  $payload  the full decoded body, stored for audit
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public string $paymentReference,
        public int $amountCents,
        public string $currency,
        public array $payload,
    ) {}
}
