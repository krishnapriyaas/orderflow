<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A webhook body with the headers that sign it. Built by FakePaymentGateway for tests.
 */
final readonly class SignedWebhook
{
    public function __construct(
        public string $body,
        public int $timestamp,
        public string $signature,
    ) {}

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-Timestamp' => (string) $this->timestamp,
            'X-Signature' => $this->signature,
        ];
    }
}
