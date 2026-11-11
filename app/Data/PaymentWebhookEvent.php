<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PaymentWebhookEvent
{
    /**
     * The payment fields are null only for event types we do not handle;
     * PaymentWebhookRequest requires them for every known type.
     *
     * @param  string  $type  raw provider type; may be a type we do not handle
     * @param  string|null  $currency  uppercased ISO code
     * @param  array<string, mixed>  $payload  the full decoded body, stored for audit
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public ?string $paymentReference,
        public ?int $amountCents,
        public ?string $currency,
        public array $payload,
    ) {}
}
