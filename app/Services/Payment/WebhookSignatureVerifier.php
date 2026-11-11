<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

/**
 * Checks the X-Signature / X-Timestamp headers of a payment webhook.
 */
final class WebhookSignatureVerifier
{
    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds,
    ) {}

    /**
     * @param  string  $rawBody  the exact request body, before any JSON decoding
     */
    public function verify(?string $signature, ?string $timestamp, string $rawBody): bool
    {
        if ($this->secret === '') {
            Log::error('Payment webhook rejected: PAYMENT_WEBHOOK_SECRET is not configured.');

            return false;
        }

        if ($signature === null || $signature === '' || $timestamp === null || ! ctype_digit($timestamp)) {
            return $this->reject('missing or malformed signature headers');
        }

        // Reject old (replayed) and future timestamps alike.
        if (abs(now()->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            return $this->reject('timestamp outside the allowed window');
        }

        $expected = WebhookSignature::sign($timestamp, $rawBody, $this->secret);

        if (! hash_equals($expected, $signature)) {
            return $this->reject('signature mismatch');
        }

        return true;
    }

    private function reject(string $reason): bool
    {
        Log::warning('Payment webhook rejected: '.$reason.'.');

        return false;
    }
}
